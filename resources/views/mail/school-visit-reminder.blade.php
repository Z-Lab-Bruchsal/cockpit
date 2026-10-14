<x-mail::message>
# {{ $registration->venue === \App\Enums\SchoolVenue::School ? 'Bald kommen wir zu Ihnen!' : 'Bald besuchen Sie uns im Z-LAB!' }}

@if ($registration->venue === \App\Enums\SchoolVenue::School)
wir kommen am {{ $registration->courseInstance->dates->first()?->formattedRange() }} mit dem Kurs zu Ihrer Klasse an die Schule.
@else
am {{ $registration->courseInstance->dates->first()?->formattedRange() }} erwarten wir Ihre Klasse bei uns. Eine Anfahrtsbeschreibung finden Sie im Anhang.
@endif

Bitte prüfen Sie noch einmal, ob die **Schülerzahl** und das **Thema** so stimmen:

@include('mail.partials.school-visit-summary', ['registration' => $registration])

Bitte bestätigen Sie kurz, dass der Termin klappt. Dort können Sie auch die Schülerzahl korrigieren oder uns eine Nachricht hinterlassen.

<x-mail::button :url="$visitUrl" color="success">
Teilnahme bestätigen
</x-mail::button>

@if ($registration->courseInstance->canStillBeRescheduled())
Falls etwas dazwischengekommen ist, können Sie den Termin bis zum {{ $registration->courseInstance->rescheduleDeadline()->format('d.m.Y') }} über denselben Link stornieren oder verschieben.
@else
Falls etwas dazwischengekommen ist, rufen Sie uns bitte an{{ \App\Models\CompanySetting::current()->contact_phone ? ': '.\App\Models\CompanySetting::current()->contact_phone : '' }}.
@endif

Viele Grüße<br>
{{ \App\Models\CompanySetting::current()->homepageTitle() }}
</x-mail::message>
