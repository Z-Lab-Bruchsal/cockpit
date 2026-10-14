<x-mail::message>
@if ($approved)
# Ihr Termin ist bestätigt!

wir freuen uns auf Ihre Klasse. {{ $registration->replacesRegistration ? 'Ihr bisheriger Termin ist damit storniert.' : '' }}
@else
# Leider klappt es mit diesem Termin nicht

Ihre Anfrage konnten wir nicht bestätigen. {{ $registration->replacesRegistration ? 'Ihr bisheriger Termin bleibt unverändert bestehen.' : 'Der Termin ist wieder freigegeben.' }}
@endif

@include('mail.partials.school-visit-summary', ['registration' => $registration])

@if ($registration->decision_note)
<x-mail::panel>
{!! nl2br(e($registration->decision_note)) !!}
</x-mail::panel>
@endif

@if ($approved && $visitUrl)
<x-mail::button :url="$visitUrl">
Termin ansehen
</x-mail::button>

@if ($registration->courseInstance->rescheduleDeadline())
Bis zum {{ $registration->courseInstance->rescheduleDeadline()->format('d.m.Y') }} können Sie den Termin über diesen Link oder in Ihrem Schulkonto selbst stornieren oder verschieben. Danach rufen Sie uns bitte an.
@endif
@elseif (! $approved)
Gerne können Sie unter [freie Termine]({{ route('homepage.schools.slots') }}) einen anderen Termin auswählen.
@endif

Viele Grüße<br>
{{ \App\Models\CompanySetting::current()->homepageTitle() }}
</x-mail::message>
