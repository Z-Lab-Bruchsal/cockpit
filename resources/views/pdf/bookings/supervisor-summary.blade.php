@extends('pdf.bookings.layout')

@section('content')
    <h1>{{ $title }}</h1>
    <div class="subtitle">{{ $periodLabel }}</div>

    <table class="data">
        <thead>
            <tr>
                <th>Benutzer</th>
                <th class="text-right">Gearbeitet</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($totals as $row)
                <tr>
                    <td>{{ $row['user']->name }}</td>
                    <td class="text-right">{{ $formatMinutes($row['minutes']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">Keine Benutzer vorhanden.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td>Gesamt</td>
                <td class="text-right">{{ $formatMinutes($totals->sum('minutes')) }}</td>
            </tr>
        </tfoot>
    </table>
@endsection
