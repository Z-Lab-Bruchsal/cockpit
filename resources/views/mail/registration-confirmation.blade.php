<x-mail::message>
# Hallo {{ $name }}!

danke für deine Anmeldung. Bitte bestätige sie mit einem Klick – erst dann ist sie gültig.

@include('mail.partials.registration-summary', ['registrations' => $registrations, 'manageUrls' => []])

<x-mail::button :url="$confirmationUrl">
Anmeldung bestätigen
</x-mail::button>

Ohne Bestätigung verfallen die reservierten Plätze nach {{ $confirmationHours }} Stunden wieder.

Du hast dich nicht angemeldet? Dann ignoriere diese E-Mail einfach.

Viele Grüße<br>
{{ \App\Models\CompanySetting::current()->homepageTitle() }}
</x-mail::message>
