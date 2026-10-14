<x-mail::message>
# Abmeldung bestätigt

@if ($enrollment)
die Anmeldung von **{{ $enrollment->displayKidName() }}** für **{{ $registration->courseInstance->displayTitle() }}** wurde storniert.
@else
deine Anmeldung für **{{ $registration->courseInstance->displayTitle() }}** wurde storniert.
@endif

Schade, dass es nicht klappt – vielleicht sehen wir uns bei einem anderen Kurs!

<x-mail::button :url="route('homepage.courses.index')">
Zu den Kursen
</x-mail::button>

Viele Grüße<br>
{{ \App\Models\CompanySetting::current()->homepageTitle() }}
</x-mail::message>
