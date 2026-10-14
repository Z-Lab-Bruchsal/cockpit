<x-mail::message>
@if ($kind === \App\Mail\SchoolLoginLinkMail::INVITATION)
# Guten Tag {{ $contact->name }},

Sie wurden eingeladen, für die **{{ $school?->name }}** Termine für Ihre Klassen bei uns zu buchen. Mit dem folgenden Button aktivieren Sie Ihren Zugang – ein Passwort brauchen Sie nicht. Später melden Sie sich einfach mit Ihrer E-Mail-Adresse an und bekommen einen Link geschickt.
@else
# Guten Tag,

vielen Dank für die Registrierung der **{{ $contact->name }}**. Bitte bestätigen Sie die Adresse über den folgenden Button. Danach können Sie Termine für Schulklassen buchen und Ihre Lehrkräfte einladen.
@endif

<x-mail::button :url="$url">
{{ $kind === \App\Mail\SchoolLoginLinkMail::INVITATION ? 'Zugang aktivieren' : 'Schulkonto bestätigen' }}
</x-mail::button>

Der Link ist {{ $validity }} gültig und kann nur einmal verwendet werden.

Sie haben diese E-Mail nicht erwartet? Dann können Sie sie einfach ignorieren.

Viele Grüße<br>
{{ \App\Models\CompanySetting::current()->homepageTitle() }}
</x-mail::message>
