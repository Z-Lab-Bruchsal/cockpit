<x-mail::message>
@if ($kind === \App\Mail\SchoolBookingUpdateMail::CANCELLED)
# Eine Schule hat ihren Termin storniert

Der Termin ist damit wieder frei.
@else
# Eine Schule hat ihren Termin bestätigt

@if ($changes)
Dabei wurde Folgendes geändert:

@foreach ($changes as $change)
- {{ \Illuminate\Support\Str::squish($change) }}
@endforeach
@else
Es gab keine Änderungen.
@endif
@endif

@include('mail.partials.school-visit-summary', ['registration' => $registration])

<x-mail::button :url="$cockpitUrl">
Im Cockpit öffnen
</x-mail::button>
</x-mail::message>
