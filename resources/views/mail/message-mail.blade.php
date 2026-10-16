<x-mail::message>
# {{ $internalMessage->subject }}

Hi,

du hast eine neue Nachricht von {{ $internalMessage->sender->name }} im Cockpit.

An: {{ $internalMessage->recipientNames() }}

{!! $internalMessage->body !!}

<x-mail::button :url="$message_url" color="primary">
Nachricht im Cockpit lesen
</x-mail::button>

Danke,<br>
{{ config('app.name') }}
</x-mail::message>
