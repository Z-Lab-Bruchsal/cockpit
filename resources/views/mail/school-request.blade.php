<x-mail::message>
# {{ $registration->replacesRegistration ? 'Eine Schule möchte ihren Termin verschieben' : 'Neue Anfrage einer Schulklasse' }}

@if ($registration->replacesRegistration)
Die **{{ $registration->school?->name }}** möchte vom {{ $registration->replacesRegistration->courseInstance->dates->first()?->formattedRange() }} auf den unten genannten Termin wechseln. Der bisherige Termin bleibt bestehen, bis du die Verschiebung freigibst. Bei einer Ablehnung bleibt alles beim Alten.
@else
Der Termin ist für die Schule reserviert und wartet auf deine Entscheidung.
@endif

@include('mail.partials.school-visit-summary', ['registration' => $registration])

## Kontakt
<x-mail::table>
| | |
|:--|:--|
| **E-Mail** | {{ str_replace('|', '/', (string) $registration->email) }} |
@if ($registration->phone)
| **Telefon** | {{ str_replace('|', '/', $registration->phone) }} |
@endif
| **Schuladresse** | {{ str_replace('|', '/', (string) $registration->school?->addressLine()) }} |
| **Funktionsadresse** | {{ str_replace('|', '/', (string) $registration->school?->email) }} |
</x-mail::table>

<x-mail::button :url="$approveUrl" color="success">
Bestätigen
</x-mail::button>

<x-mail::button :url="$declineUrl" color="error">
Ablehnen
</x-mail::button>

Du kannst die Anfrage auch im Cockpit unter „Anmeldungen“ entscheiden.
</x-mail::message>
