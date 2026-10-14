<x-mail::message>
# Deine Anmeldung ist eingegangen

danke! Für **{{ $registration->courseInstance->displayTitle() }}** sind nicht mehr genug Plätze für alle Geschwister frei. Wir prüfen gerade, ob trotzdem alle teilnehmen können, und melden uns so schnell wie möglich bei dir.

@include('mail.partials.registration-summary', ['registrations' => collect([$registration]), 'manageUrls' => []])

Viele Grüße<br>
{{ \App\Models\CompanySetting::current()->homepageTitle() }}
</x-mail::message>
