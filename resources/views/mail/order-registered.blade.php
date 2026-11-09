<x-mail::message>
# Bestellung eingegangen

Hi,

es gibt eine neue Bestellung:
* Name: {{ $order->name }}
* Anzahl: {{ $order->count }}
* <a href="{{ $order->url }}">URL zum Artikel</a>
* Bestellt von: {{ $order->user->name }}

<x-mail::button :url="$url_ordered" color="success">
Habe bestellt
</x-mail::button>

<x-mail::button :url="$url_order" color="primary">
Bestellung im Cockpit ansehen
</x-mail::button>

Bestellt? Preis und Bestellnummer trägst du in der [Abarbeitungsliste]({{ $url_process }}) ein.

Danke,<br>
{{ config('app.name') }}
</x-mail::message>
