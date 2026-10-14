<x-mail::message>
# Vielen Dank für Ihre Anfrage!

wir haben {{ $registrations->count() === 1 ? 'den folgenden Termin' : 'die folgenden Termine' }} für Ihre Klasse reserviert. Die Buchung ist noch nicht verbindlich: Die zuständige Kursleitung prüft die Anfrage und meldet sich per E-Mail bei Ihnen.

@foreach ($registrations as $registration)
@include('mail.partials.school-visit-summary', ['registration' => $registration])
@if ($registration->replacesRegistration)
*Verschiebung: Ihr bisheriger Termin ({{ $registration->replacesRegistration->courseInstance->dates->first()?->formattedRange() }}) bleibt bestehen, bis der neue Termin bestätigt ist.*
@endif

@endforeach

Ihre Buchungen finden Sie jederzeit in Ihrem [Schulkonto]({{ route('homepage.schools.account') }}).

Viele Grüße<br>
{{ \App\Models\CompanySetting::current()->homepageTitle() }}
</x-mail::message>
