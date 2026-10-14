<x-mail::message>
# {{ $isNewContact ? 'Willkommen!' : 'Hallo'.($name ? ' '.$name : '').'!' }}

@if ($isNewContact)
schön, dass du dich bei uns registrieren möchtest. Bitte bestätige deine E-Mail-Adresse über den folgenden Button – danach kannst du deine Kinder und ihre Interessen hinterlegen und sie zu Kursen anmelden.
@else
hier ist dein Link zum Anmelden. Ein Passwort brauchst du nicht.
@endif

<x-mail::button :url="$url">
{{ $isNewContact ? 'Registrierung bestätigen' : 'Jetzt anmelden' }}
</x-mail::button>

Der Link ist {{ $lifetimeMinutes }} Minuten gültig und kann nur einmal verwendet werden.

Du hast diese E-Mail nicht angefordert? Dann kannst du sie einfach ignorieren.

Viele Grüße<br>
{{ \App\Models\CompanySetting::current()->homepageTitle() }}
</x-mail::message>
