<x-mail::message>
@if ($siblingsApproved)
# Gute Nachrichten!

alle Geschwister dürfen teilnehmen – die Anmeldung ist jetzt verbindlich. Wir freuen uns auf euch!
@else
# Deine Anmeldung ist bestätigt!

Wir freuen uns auf euch. Hier noch einmal alles im Überblick:
@endif

@include('mail.partials.registration-summary', ['registrations' => $registrations, 'manageUrls' => $manageUrls])

Falls ihr doch nicht kommen könnt, storniert die Anmeldung bitte über den Link oben – dann kann ein anderes Kind nachrücken.

Viele Grüße<br>
{{ \App\Models\CompanySetting::current()->homepageTitle() }}
</x-mail::message>
