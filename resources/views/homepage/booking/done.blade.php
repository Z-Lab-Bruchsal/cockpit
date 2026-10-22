<x-homepage.layout title="Anmeldung">
    <div class="mx-auto max-w-lg px-4 py-12 text-center sm:py-16">
        @if (session('registration_awaits_approval'))
            <h1 class="text-3xl font-bold tracking-tight text-brand-heading">Danke für die Anmeldung!</h1>
            <p class="mt-3 text-gray-600">Für alle Geschwister waren nicht mehr genug Plätze frei. Wir prüfen das und melden uns per E-Mail{{ $confirmedDirectly ? '' : ' – bitte bestätige vorher noch den Link in der E-Mail, die wir dir gerade geschickt haben' }}.</p>
        @elseif ($confirmedDirectly)
            <h1 class="text-3xl font-bold tracking-tight text-brand-heading">Angemeldet!</h1>
            <p class="mt-3 text-gray-600">Deine Anmeldung ist bestätigt. Eine Zusammenfassung haben wir dir per E-Mail geschickt.</p>
            <a href="{{ route('homepage.account') }}" class="mt-8 inline-block rounded-lg bg-brand px-4 py-3 font-semibold text-brand-contrast hover:opacity-90">Zu meinen Anmeldungen</a>
        @else
            <h1 class="text-3xl font-bold tracking-tight text-brand-heading">Fast geschafft!</h1>
            <p class="mt-3 text-gray-600">
                Wir haben dir eine E-Mail{{ $email ? ' an '.$email : '' }} geschickt. Bitte klicke auf den Link darin, um die Anmeldung zu bestätigen – erst dann ist sie verbindlich.
            </p>
            <p class="mt-3 text-sm text-gray-500">Keine E-Mail bekommen? Schau bitte auch im Spam-Ordner nach.</p>
        @endif
    </div>
</x-homepage.layout>
