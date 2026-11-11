<x-mail::message>
# {{ $internalMessage->subject }}

Hi,

@if ($internalMessage->isExternal())
an das Cockpit-Postfach ist eine E-Mail von {{ $internalMessage->senderName() }} eingegangen.
@else
du hast eine neue interne Nachricht von {{ $internalMessage->senderName() }} im Cockpit.
@endif

An: {{ $internalMessage->recipientNames() }}

{{ \Filament\Forms\Components\RichEditor\RichContentRenderer::make($internalMessage->body) }}

<x-mail::button :url="$message_url" color="primary">
Nachricht im Cockpit lesen
</x-mail::button>

Danke,<br>
{{ config('app.name') }}
</x-mail::message>
