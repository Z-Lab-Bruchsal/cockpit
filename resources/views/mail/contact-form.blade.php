<x-mail::message>
# {{ $topic->getLabel() }}

Neue Nachricht über das Kontaktformular der Homepage.

<x-mail::table>
| | |
|:--|:--|
| **Name** | {{ str_replace('|', '/', $senderName) }} |
| **E-Mail** | {{ str_replace('|', '/', $senderEmail) }} |
@if ($senderPhone)
| **Telefon** | {{ str_replace('|', '/', $senderPhone) }} |
@endif
@if ($oldEmail)
| **Bisherige E-Mail** | {{ str_replace('|', '/', $oldEmail) }} |
@endif
</x-mail::table>

<x-mail::panel>
{!! nl2br(e($body)) !!}
</x-mail::panel>

Antworte einfach auf diese E-Mail, um {{ $senderName }} direkt zu erreichen.
</x-mail::message>
