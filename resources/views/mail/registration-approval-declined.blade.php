<x-mail::message>
# Leider nicht für alle Geschwister

für **{{ $registration->courseInstance->displayTitle() }}** können wir leider nicht alle Geschwister aufnehmen.

@if ($isOnWaitlist)
Wir haben euch deshalb gemeinsam auf die Warteliste gesetzt. Sobald genug Plätze frei werden, bekommst du eine E-Mail und hast dann {{ $registration->courseInstance->waitlistOfferHours() }} Stunden Zeit, die Plätze anzunehmen.
@else
Die Anmeldung ist damit leider nicht zustande gekommen. Schau gern bei unseren anderen Kursen vorbei!

<x-mail::button :url="route('homepage.courses.index')">
Zu den Kursen
</x-mail::button>
@endif

Viele Grüße<br>
{{ \App\Models\CompanySetting::current()->homepageTitle() }}
</x-mail::message>
