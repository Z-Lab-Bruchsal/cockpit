<x-mail::message>
# {{ $subject }}

Hi,

{{ $message }}

{{ $notiz }}

## <a href="{{ $todo_url }}">{{ $todo->name }}:</a>

Erstellt von: {{ $todo->user->name }}
Zugewiesen: {{ $todo->todoable->name }}

Inhalt:
{!! $todo->content !!}

<x-mail::button :url="$todo_url" color="primary">
Todo im Cockpit ansehen
</x-mail::button>

Danke,<br>
{{ config('app.name') }}
</x-mail::message>
