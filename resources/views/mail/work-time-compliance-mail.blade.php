<x-mail::message>
# Hinweis zu deiner Pausenzeit

Hi {{ $user->name }},

am {{ $date->format('d.m.Y') }} wurden folgende Hinweise zu deinen Pausenzeiten festgestellt:

@foreach ($warnings as $warning)
- {{ $warning }}
@endforeach

<x-mail::button :url="$time_entries_url" color="primary">
Zeiten im Cockpit ansehen
</x-mail::button>

Danke,<br>
{{ config('app.name') }}
</x-mail::message>
