<x-mail::message>
# Gute Nachrichten!

im Kurs **{{ $registration->courseInstance->displayTitle() }}** ist ein Platz frei geworden – ihr seid von der Warteliste nachgerückt.

@include('mail.partials.registration-summary', ['registrations' => collect([$registration]), 'manageUrls' => []])

<x-mail::button :url="$confirmationUrl">
Platz annehmen
</x-mail::button>

Bitte nimm den Platz innerhalb von {{ $confirmationHours }} Stunden an, sonst geht er an die nächste Familie auf der Warteliste.

Viele Grüße<br>
{{ \App\Models\CompanySetting::current()->homepageTitle() }}
</x-mail::message>
