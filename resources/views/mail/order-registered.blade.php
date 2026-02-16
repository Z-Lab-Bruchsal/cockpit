<x-mail::message>
# Bestellung eingegange

Hi,

es gibt eine neue Bestellung.

<x-mail::button :url="''">
Button Text
</x-mail::button>

Danke,<br>
{{ config('app.name') }}
</x-mail::message>
