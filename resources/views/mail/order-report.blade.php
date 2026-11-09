<x-mail::message>
# Bestellungen {{ $from->format('d.m.Y') }} – {{ $to->copy()->subSecond()->format('d.m.Y') }}

@if ($count === 0)
In diesem Zeitraum wurde nichts bestellt.
@else
{{ $count }} {{ $count === 1 ? 'Position wurde' : 'Positionen wurden' }} bestellt, zusammen **{{ \Illuminate\Support\Number::currency($total, 'EUR', 'de') }}**.

<x-mail::table>
| Händler | Summe |
|:--------|------:|
@foreach ($totals as $merchant => $sum)
| {{ $merchant }} | {{ \Illuminate\Support\Number::currency($sum, 'EUR', 'de') }} |
@endforeach
</x-mail::table>

Die Einzelpositionen stehen in den Anhängen (Excel und PDF).
@endif

{{ config('app.name') }}
</x-mail::message>
