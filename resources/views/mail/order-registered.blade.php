<x-mail::message>
# Bestellung eingegangen

Hi,

es gibt eine neue Bestellung:
* Name: {{ $order->name }}
* Anzahl: {{ $order->count }}
* <a href="{{ $order->url }}">{{ $order->url }}</a>
* Bestellt von: {{ $order->user->name }}

<x-mail::button :url="$url">
Zur Bestellung
</x-mail::button>

Danke,<br>
{{ config('app.name') }}
</x-mail::message>
