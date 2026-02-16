<x-mail::message>
# Bestellung angekommen

Eine deiner Bestellungen ist angekommen.
Bitte nicht vergessen, die Bestellung "abzuhaken"

<x-mail::button :url="''">
Button Text
</x-mail::button>

Danke,<br>
{{ config('app.name') }}
</x-mail::message>
