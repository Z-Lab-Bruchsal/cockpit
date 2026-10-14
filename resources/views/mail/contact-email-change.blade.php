<x-mail::message>
# Hallo{{ $name ? ' '.$name : '' }}!

@if ($step === \App\Mail\ContactEmailChangeMail::STEP_CONFIRM_OLD)
du möchtest die E-Mail-Adresse deines Kontos auf **{{ $newEmail }}** ändern. Bitte bestätige das zuerst hier. Danach schicken wir einen zweiten Link an die neue Adresse.

<x-mail::button :url="$url">
Änderung bestätigen
</x-mail::button>

Der Link ist {{ $lifetimeHours }} Stunden gültig. Du hast keine Änderung angefordert? Dann ignoriere diese E-Mail einfach – es passiert nichts.
@elseif ($step === \App\Mail\ContactEmailChangeMail::STEP_CONFIRM_NEW)
bitte bestätige, dass dies deine neue E-Mail-Adresse ist. Erst danach wird sie für dein Konto übernommen.

<x-mail::button :url="$url">
Neue Adresse bestätigen
</x-mail::button>

Der Link ist {{ $lifetimeHours }} Stunden gültig.
@else
die E-Mail-Adresse deines Kontos wurde auf **{{ $newEmail }}** geändert. Mails von uns gehen ab sofort an diese Adresse.

Das warst du nicht? Dann melde dich bitte umgehend bei uns{{ $contactPhone ? ' unter '.$contactPhone : '' }}{{ $contactEmail ? ' oder per E-Mail an '.$contactEmail : '' }}.
@endif

Viele Grüße<br>
{{ $signature }}
</x-mail::message>
