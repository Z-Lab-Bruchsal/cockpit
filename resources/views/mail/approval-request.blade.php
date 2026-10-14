@php
    $instance = $registration->courseInstance;
    $activeEnrollments = $registration->activeEnrollments;
@endphp
<x-mail::message>
# Freigabe nötig

@if ($registration->awaitsSiblingApproval())
Eine Familie möchte **{{ $activeEnrollments->count() }} Geschwister** anmelden, es sind aber nur noch **{{ max(0, $instance->capacity - $instance->takenSeats() + $activeEnrollments->count()) }} Plätze** frei. Bei einer Freigabe dürfen alle Geschwister teilnehmen und der Kurs wird entsprechend überbucht. Bei einer Ablehnung {{ $instance->waitlist_enabled ? 'kommt die Familie gemeinsam auf die Warteliste' : 'wird die Anmeldung abgelehnt' }}.
@else
Auf der Warteliste ist jetzt eine Familie mit **{{ $activeEnrollments->count() }} Kindern** an der Reihe, es sind aber nur **{{ $instance->freeSeats() }} Plätze** frei geworden. Bei einer Freigabe bekommt die Familie alle Plätze angeboten und der Kurs wird entsprechend überbucht. Bei einer Ablehnung gehen die freien Plätze an die nächsten Familien auf der Warteliste – diese Familie bleibt vorne auf der Warteliste.
@endif

## {{ $instance->displayTitle() }}
@foreach ($instance->dates as $date)
- {{ $date->formattedRange() }}
@endforeach

Belegung: {{ $instance->takenSeats() }} von {{ $instance->capacity }} Plätzen, {{ $instance->waitlistCount() }} auf der Warteliste.

## Familie
<x-mail::table>
| | |
|:--|:--|
| **Kontakt** | {{ str_replace('|', '/', $registration->displayContactName()) }} |
| **E-Mail** | {{ str_replace('|', '/', (string) $registration->email) }} |
@if ($registration->phone)
| **Telefon** | {{ str_replace('|', '/', $registration->phone) }} |
@endif
@foreach ($activeEnrollments as $enrollment)
| **Kind {{ $loop->iteration }}** | {{ str_replace('|', '/', $enrollment->displayKidName()) }}{{ $enrollment->kidAge($instance->dates->first()?->starts_at) !== null ? ' ('.$enrollment->kidAge($instance->dates->first()?->starts_at).' Jahre)' : '' }} |
@endforeach
</x-mail::table>

@if ($registration->message)
<x-mail::panel>
{!! nl2br(e($registration->message)) !!}
</x-mail::panel>
@endif

<x-mail::button :url="$approveUrl" color="success">
Freigeben
</x-mail::button>

<x-mail::button :url="$declineUrl" color="error">
Ablehnen
</x-mail::button>

Du kannst die Anfrage auch im Cockpit unter „Anmeldungen“ entscheiden.
</x-mail::message>
