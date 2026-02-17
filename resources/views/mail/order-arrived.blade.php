<x-mail::message>
# Bestellung angekommen

Eine deiner Bestellungen ist angekommen.
Bitte nicht vergessen, die Bestellung "abzuhaken", indem du auf "angenommen" drückst

<x-mail::button :url="'https://z-lab-cockpit.digital-infinity.de/orders'">
Zum Cockpit
</x-mail::button>

Danke,<br>
{{ config('app.name') }}
</x-mail::message>
