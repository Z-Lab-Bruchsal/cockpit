<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #1a202c; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        h2 { font-size: 13px; margin-top: 24px; }
        .subtitle { color: #555555; margin-bottom: 16px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 5px 6px; font-size: 10px; text-align: left; vertical-align: top; }
        table.data th { background-color: #f0f0f0; }
        .text-right { text-align: right; }
        tfoot td { font-weight: bold; background-color: #f7fafc; }
        .footer-note { margin-top: 20px; font-size: 9px; color: #888888; }
    </style>
</head>
<body>
    @include('pdf.bookings.partials.header', ['company' => $company, 'recipient' => null])

    <h1>Bestellungen</h1>
    <div class="subtitle">{{ $from->format('d.m.Y') }} – {{ $to->copy()->subSecond()->format('d.m.Y') }} · {{ $orders->count() }} {{ $orders->count() === 1 ? 'Position' : 'Positionen' }}</div>

    @if ($orders->isEmpty())
        <p>In diesem Zeitraum wurde nichts bestellt.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    @foreach ($headings as $index => $heading)
                        <th @class(['text-right' => in_array($columns[$index], ['count', 'price'], true)])>{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $index => $value)
                            @php($column = $columns[$index])
                            <td @class(['text-right' => in_array($column, ['count', 'price'], true)])>
                                @if ($column === 'price')
                                    {{ $money($value) }}
                                @elseif (in_array($column, ['cockpit_url', 'product_url'], true))
                                    @if ($value)
                                        <a href="{{ $value }}">öffnen</a>
                                    @else
                                        –
                                    @endif
                                @else
                                    {{ $value ?? '–' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            @if ($priceIndex !== null)
                <tfoot>
                    <tr>
                        @if ($priceIndex > 0)
                            <td colspan="{{ $priceIndex }}">Gesamt</td>
                        @endif
                        <td class="text-right">{{ $money($total) }}</td>
                        @if (count($columns) - $priceIndex - 1 > 0)
                            <td colspan="{{ count($columns) - $priceIndex - 1 }}"></td>
                        @endif
                    </tr>
                </tfoot>
            @endif
        </table>

        <h2>Summen je Händler</h2>
        <table class="data" style="width: 50%;">
            <tbody>
                @foreach ($totals as $merchant => $sum)
                    <tr>
                        <td>{{ $merchant }}</td>
                        <td class="text-right">{{ $money($sum) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer-note">Erstellt am {{ $generatedAt->format('d.m.Y H:i') }} Uhr</div>
</body>
</html>
