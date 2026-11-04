<x-mail::message>
# Hallo {{ $recipientName }},

über deine Visitenkarte auf der Homepage hat dir jemand geschrieben.

<x-mail::table>
| | |
|:--|:--|
| **Name** | {{ str_replace('|', '/', $senderName) }} |
| **E-Mail** | {{ str_replace('|', '/', $senderEmail) }} |
</x-mail::table>

<x-mail::panel>
{!! nl2br(e($body)) !!}
</x-mail::panel>

Antworte einfach auf diese E-Mail, um {{ $senderName }} direkt zu erreichen. Deine E-Mail-Adresse wird dabei sichtbar.
</x-mail::message>
