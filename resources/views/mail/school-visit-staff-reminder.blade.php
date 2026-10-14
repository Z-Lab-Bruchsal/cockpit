@php
    $class = $registration->schoolClass();
    $school = $registration->school;
@endphp
<x-mail::message>
# Erinnerung: Schulklasse am {{ $registration->courseInstance->dates->first()?->starts_at->format('d.m.Y') }}

@include('mail.partials.school-visit-summary', ['registration' => $registration])

## Schule und Ansprechpartner
<x-mail::table>
| | |
|:--|:--|
| **Schule** | {{ str_replace('|', '/', (string) $school?->name) }} |
| **Adresse** | {{ str_replace('|', '/', (string) $school?->addressLine()) }} |
| **Funktionsadresse** | {{ str_replace('|', '/', (string) $school?->email) }} |
@if ($school?->phone)
| **Telefon Schule** | {{ str_replace('|', '/', $school->phone) }} |
@endif
@if ($school?->contact_person)
| **Ansprechpartner Schule** | {{ str_replace('|', '/', $school->contact_person) }} |
@endif
| **Lehrkraft** | {{ str_replace('|', '/', $class['teacher_name']) }} |
| **E-Mail Lehrkraft** | {{ str_replace('|', '/', (string) $class['teacher_email']) }} |
@if ($class['teacher_phone'])
| **Telefon Lehrkraft** | {{ str_replace('|', '/', $class['teacher_phone']) }} |
@endif
| **Teilnahme bestätigt** | {{ $registration->teacher_confirmed_at ? 'ja, am '.$registration->teacher_confirmed_at->format('d.m.Y H:i') : 'noch nicht' }} |
</x-mail::table>

@if (filled($registration->school_class_data['teacher_remark'] ?? null))
**Anmerkung der Lehrkraft:**
<x-mail::panel>
{!! nl2br(e($registration->school_class_data['teacher_remark'])) !!}
</x-mail::panel>
@endif

@if ($registration->courseInstance->internalNotes->isNotEmpty())
## Notizen
@foreach ($registration->courseInstance->internalNotes as $note)
- *{{ $note->created_at->format('d.m.Y H:i') }}{{ $note->user ? ', '.$note->user->name : '' }}:* {{ \Illuminate\Support\Str::squish($note->body) }}
@endforeach
@endif

<x-mail::button :url="$cockpitUrl">
Im Cockpit öffnen
</x-mail::button>

Im Cockpit kannst du diese Erinnerung verschieben und Notizen zur Durchführung ergänzen, z. B. aus einem Gespräch mit der Schule.
</x-mail::message>
