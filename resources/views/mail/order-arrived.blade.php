<x-mail::message>
# Bestellung angekommen

Eine deiner Bestellungen ist angekommen.

* Name: {{ $order->name }}
* Anzahl: {{ $order->count }}
* <a href="{{ $order->url }}">{{ $order->url }}</a>

Bitte nicht vergessen, die Bestellung "abzuhaken", indem du auf "angenommen" drückst

<x-mail::button :url="$url">
Zur Bestellung
</x-mail::button>

Danke,<br>
{{ config('app.name') }}
</x-mail::message>
