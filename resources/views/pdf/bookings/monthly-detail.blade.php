@extends('pdf.bookings.layout')

@section('content')
    <h1>Stundennachweis</h1>
    <div class="subtitle">{{ $user->name }} &middot; {{ $monthLabel }} {{ $year }}</div>

    <table class="data">
        <thead>
            <tr>
                <th>Datum</th>
                <th>Von</th>
                <th>Bis</th>
                <th class="text-right">Dauer</th>
                <th>Notiz</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($bookings as $booking)
                <tr>
                    <td>{{ $booking->start_at->copy()->setTimezone($timezone)->format('d.m.Y') }}</td>
                    <td>{{ $booking->start_at->copy()->setTimezone($timezone)->format('H:i') }}</td>
                    <td>{{ $booking->end_at ? $booking->end_at->copy()->setTimezone($timezone)->format('H:i') : 'läuft' }}</td>
                    <td class="text-right">{{ $formatMinutes($booking->worked_minutes) }}</td>
                    <td>{{ $booking->note }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Keine Buchungen in diesem Zeitraum.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Gesamt</td>
                <td class="text-right">{{ $formatMinutes($totalMinutes) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <table class="signatures">
        <tr>
            <td>Datum, Unterschrift Mitarbeiter</td>
            <td>Datum, Unterschrift Vorgesetzter</td>
        </tr>
    </table>
@endsection
