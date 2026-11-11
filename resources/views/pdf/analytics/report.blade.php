@php
    /**
     * Horizontal bar table for a label => value distribution.
     */
    $bars = function (array $values, callable $format): string {
        $max = max([1, ...array_values($values)]);
        $html = '<table class="bars">';

        foreach ($values as $label => $value) {
            $width = $value > 0 ? max(1, round($value / $max * 100)) : 0;
            $html .= '<tr><td class="bar-label">'.e($label).'</td>'
                .'<td class="bar-cell"><div class="bar" style="width: '.$width.'%;"></div></td>'
                .'<td class="bar-value">'.e($format($value)).'</td></tr>';
        }

        return $html.'</table>';
    };
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #1a202c; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        h2 { font-size: 13px; margin-top: 22px; margin-bottom: 6px; page-break-after: avoid; }
        .subtitle { color: #555555; margin-bottom: 12px; }
        .section { page-break-inside: avoid; }
        table.figures { width: 100%; border-collapse: collapse; }
        table.figures td { border: 1px solid #dddddd; padding: 6px; vertical-align: top; width: 20%; }
        .figure-label { font-size: 9px; color: #666666; text-transform: uppercase; }
        .figure-value { font-size: 15px; font-weight: bold; margin-top: 2px; }
        .figure-note { font-size: 9px; color: #666666; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 4px 6px; font-size: 10px; text-align: left; }
        table.data th { background-color: #f0f0f0; }
        .text-right { text-align: right; }
        .danger { color: #b91c1c; font-weight: bold; }
        table.bars { width: 100%; border-collapse: collapse; }
        table.bars td { padding: 2px 4px; font-size: 10px; }
        .bar-label { width: 22%; }
        .bar-cell { width: 63%; }
        .bar-value { width: 15%; text-align: right; }
        .bar { height: 10px; background-color: #d6246e; }
        .muted { color: #777777; }
        .footer-note { margin-top: 20px; font-size: 9px; color: #888888; }
    </style>
</head>
<body>
    @include('pdf.bookings.partials.header', ['company' => $company, 'recipient' => null])

    <h1>Auswertung</h1>
    <div class="subtitle">{{ $filtersDescription }}</div>

    @if (in_array('courses', $sections, true))
        <div class="section">
            <h2>Kurse</h2>
            <table class="figures">
                <tr>
                    <td><div class="figure-label">Kurse</div><div class="figure-value">{{ $number($courses['courses']) }}</div></td>
                    <td><div class="figure-label">Durchführungen</div><div class="figure-value">{{ $number($courses['instances']) }}</div><div class="figure-note">{{ $courses['cancelled'] }} abgesagt</div></td>
                    <td><div class="figure-label">Belegte Plätze</div><div class="figure-value">{{ $number($courses['occupied']) }} / {{ $number($courses['capacity']) }}</div></td>
                    <td><div class="figure-label">Auslastung</div><div class="figure-value">{{ $courses['utilization'] === null ? '–' : $number($courses['utilization'], 1).' %' }}</div></td>
                </tr>
            </table>
        </div>
    @endif

    @if (in_array('participants', $sections, true))
        <div class="section">
            <h2>Teilnehmende</h2>
            <table class="figures">
                <tr>
                    <td><div class="figure-label">Kinder</div><div class="figure-value">{{ $number($participants['kids']) }}</div></td>
                    <td><div class="figure-label">Anmeldungen</div><div class="figure-value">{{ $number($participants['enrollments']) }}</div></td>
                    <td><div class="figure-label">Warteliste</div><div class="figure-value">{{ $number($participants['waitlisted']) }}</div></td>
                    <td><div class="figure-label">Ø Alter</div><div class="figure-value">{{ $participants['average_age'] === null ? '–' : $number($participants['average_age'], 1).' J.' }}</div></td>
                    <td><div class="figure-label">Schulklassen</div><div class="figure-value">{{ $number($participants['school_classes']) }}</div><div class="figure-note">{{ $number($participants['students']) }} Schüler/innen</div></td>
                </tr>
            </table>
        </div>
    @endif

    @if (in_array('ages', $sections, true))
        <div class="section">
            <h2>Alter der Kinder <span class="muted">(Anmeldungen nach Alter zu Kursbeginn)</span></h2>
            {!! $bars($ages, fn ($value) => $number($value)) !!}
        </div>
    @endif

    @if (in_array('months', $sections, true))
        <div class="section">
            <h2>Anmeldungen pro Monat</h2>
            {!! $bars($months, fn ($value) => $number($value)) !!}
        </div>
    @endif

    @if (in_array('interests', $sections, true))
        <div class="section">
            <h2>Anmeldungen nach Interesse</h2>
            @if ($interests === [])
                <p class="muted">Keine Anmeldungen im Zeitraum.</p>
            @else
                {!! $bars($interests, fn ($value) => $number($value)) !!}
            @endif
        </div>
    @endif

    @if (in_array('top_courses', $sections, true))
        <div class="section">
            <h2>Top-Kurse</h2>
            @if ($topCourses === [])
                <p class="muted">Keine Durchführungen im Zeitraum.</p>
            @else
                <table class="data">
                    <thead>
                        <tr><th>Kurs</th><th class="text-right">Durchführungen</th><th class="text-right">Anmeldungen</th><th class="text-right">Warteliste</th><th class="text-right">Auslastung</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($topCourses as $course)
                            <tr>
                                <td>{{ $course['name'] }}</td>
                                <td class="text-right">{{ $number($course['instances']) }}</td>
                                <td class="text-right">{{ $number($course['participants']) }}</td>
                                <td class="text-right">{{ $number($course['waitlisted']) }}</td>
                                <td class="text-right">{{ $course['utilization'] === null ? '–' : $number($course['utilization'], 1).' %' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    @if (in_array('top_kids', $sections, true))
        <div class="section">
            <h2>Top-Kinder</h2>
            @if ($topKids === [])
                <p class="muted">Keine Teilnahmen im Zeitraum.</p>
            @else
                <table class="data">
                    <thead>
                        <tr><th>Kind</th><th class="text-right">Kurse</th><th class="text-right">Alter</th><th>Zuletzt</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($topKids as $kid)
                            <tr>
                                <td>{{ $kid['name'] }}</td>
                                <td class="text-right">{{ $number($kid['courses']) }}</td>
                                <td class="text-right">{{ $kid['age'] ?? '–' }}</td>
                                <td>{{ $kid['last_course'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    @if (in_array('orders', $sections, true))
        <div class="section">
            <h2>Kosten aus Bestellungen</h2>
            <table class="figures">
                <tr>
                    <td><div class="figure-label">Summe</div><div class="figure-value">{{ $money($orders['total']) }}</div></td>
                    <td><div class="figure-label">Positionen</div><div class="figure-value">{{ $number($orders['count']) }}</div></td>
                    <td><div class="figure-label">Ø je Position</div><div class="figure-value">{{ $money($orders['average']) }}</div></td>
                    <td><div class="figure-label">Ohne Preis</div><div class="figure-value">{{ $number($orders['without_price']) }}</div></td>
                </tr>
            </table>
            <h2>Bestellkosten pro Monat</h2>
            {!! $bars($orderMonths, fn ($value) => $money($value)) !!}
            @if ($orderMerchants !== [])
                <h2>Bestellkosten nach Händler</h2>
                {!! $bars($orderMerchants, fn ($value) => $money($value)) !!}
            @endif
        </div>
    @endif

    @if (in_array('work_hours', $sections, true))
        <div class="section">
            <h2>Stunden der Übungsleiter</h2>
            @if ($workHours === [])
                <p class="muted">Keine Mitarbeiter mit vereinbarten Wochenstunden.</p>
            @else
                <table class="data">
                    <thead>
                        <tr><th>Mitarbeiter</th><th class="text-right">Soll / Woche</th><th class="text-right">Ø / Woche</th><th class="text-right">Höchste Woche</th><th class="text-right">Wochen über Soll</th><th class="text-right">Mehrstunden</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($workHours as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td class="text-right">{{ $number($row['weekly_hours'], 2) }} h</td>
                                <td class="text-right">{{ $duration($row['average_week_minutes']) }}</td>
                                <td @class(['text-right', 'danger' => $row['max_week_minutes'] > $row['target_minutes']])>{{ $duration($row['max_week_minutes']) }}</td>
                                <td @class(['text-right', 'danger' => $row['weeks_over'] > 0])>{{ $row['weeks_over'] }} von {{ count($row['weeks']) }}</td>
                                <td @class(['text-right', 'danger' => $row['overtime_minutes'] > 0])>{{ $row['overtime_minutes'] > 0 ? '+ '.$duration($row['overtime_minutes']) : '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @foreach ($workHours as $row)
                    @if ($row['weeks_over'] > 0)
                        <p><strong>{{ $row['name'] }}</strong> – Wochen über {{ $number($row['weekly_hours'], 2) }} h:
                            {{ collect($row['weeks'])->filter(fn ($minutes) => $minutes > $row['target_minutes'])->map(fn ($minutes, $week) => $week.' ('.$duration($minutes).')')->join(', ') }}
                        </p>
                    @endif
                @endforeach
            @endif
        </div>
    @endif

    <div class="footer-note">Erstellt am {{ $generatedAt->format('d.m.Y H:i') }}</div>
</body>
</html>
