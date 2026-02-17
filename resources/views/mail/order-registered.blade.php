<x-mail::message>
# Bestellung eingegangen

Hi,

es gibt eine neue Bestellung.

<x-mail::button :url="'https://z-lab-cockpit.digital-infinity.de/orders'">
Zum Cockpit
</x-mail::button>

Danke,<br>
{{ config('app.name') }}
</x-mail::message>
