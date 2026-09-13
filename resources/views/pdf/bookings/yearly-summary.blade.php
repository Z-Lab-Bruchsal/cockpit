@extends('pdf.bookings.layout')

@section('content')
    <h1>Jahresübersicht</h1>
    <div class="subtitle">{{ $user->name }} &middot; {{ $year }}</div>

    <table class="data">
        <thead>
            <tr>
                <th>Monat</th>
                <th class="text-right">Gearbeitet</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($monthlyTotals as $month => $minutes)
                <tr>
                    <td>{{ $monthNames[$month] }}</td>
                    <td class="text-right">{{ $formatMinutes($minutes) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>Gesamt</td>
                <td class="text-right">{{ $formatMinutes($totalMinutes) }}</td>
            </tr>
        </tfoot>
    </table>
@endsection
