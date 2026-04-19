<x-mail::message>
# Bestellung angekommen

Eine deiner Bestellungen ist angekommen.

* Name: {{ $order->name }}
* Anzahl: {{ $order->count }}
* <a href="{{ $order->url }}">URL zum Artikel</a>

Bitte nicht vergessen, die Bestellung "abzuhaken", indem du auf "angenommen" drückst

<x-mail::button :url="$url_taken" color="success">
Hab's genommen
</x-mail::button>

<x-mail::button :url="$url_order" color="primary">
Bestellung im Cockpit ansehen
</x-mail::button>

Danke,<br>
{{ config('app.name') }}
</x-mail::message>
