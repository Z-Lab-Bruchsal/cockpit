<x-mail::message>
# Hallo {{ $contact->name }}!

{{ $items->count() === 1 ? 'es gibt einen neuen Kurs, der passen könnte:' : 'es gibt '.$items->count().' neue Kurse, die passen könnten:' }}

@foreach ($items as $item)
@php($instance = $item['instance'])
## {{ $instance->displayTitle() }}

@if (count($item['kids']))
*Passt zu {{ implode(', ', $item['kids']) }}*

@endif
@if ($instance->course->teaser)
{{ $instance->course->teaser }}

@endif
{{ $instance->dates->first()?->formattedRange() }}{{ $instance->dates->count() > 1 ? ' und '.($instance->dates->count() - 1).' weitere Termine' : '' }}<br>
{{ $instance->course->ageRangeLabel() ? 'Für '.$instance->course->ageRangeLabel().'. ' : '' }}{{ $instance->freeSeats() > 0 ? 'Noch '.$instance->freeSeats().' von '.$instance->capacity.' Plätzen frei.' : ($instance->waitlist_enabled ? 'Schon voll – Warteliste möglich.' : 'Leider schon ausgebucht.') }}

[Mehr erfahren & anmelden]({{ $instance->course->url() }}#durchfuehrung-{{ $instance->id }})

@endforeach

Viele Grüße<br>
{{ \App\Models\CompanySetting::current()->homepageTitle() }}

<x-slot:subcopy>
Du bekommst diese E-Mail, weil du Infos zu neuen Kursen abonniert hast. Wie oft wir schreiben, kannst du [in deinem Konto einstellen]({{ $settingsUrl }}). [Keine Kursinfos mehr erhalten]({{ $unsubscribeUrl }})
</x-slot:subcopy>
</x-mail::message>
