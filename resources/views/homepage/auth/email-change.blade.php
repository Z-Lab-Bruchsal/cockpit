<x-homepage.layout title="E-Mail-Adresse ändern">
    <div class="mx-auto max-w-md px-4 py-12 text-center sm:py-16">
        @switch($result)
            @case(\App\Services\Contacts\EmailChangeService::RESULT_OLD_CONFIRMED)
                <h1 class="text-3xl font-bold tracking-tight text-brand-heading">Danke!</h1>
                <p class="mt-3 text-gray-600">Wir haben jetzt einen zweiten Link an deine <strong>neue</strong> Adresse geschickt. Bitte bestätige die Änderung auch dort.</p>
                @break

            @case(\App\Services\Contacts\EmailChangeService::RESULT_COMPLETED)
                <h1 class="text-3xl font-bold tracking-tight text-brand-heading">E-Mail-Adresse geändert</h1>
                <p class="mt-3 text-gray-600">Deine neue Adresse ist ab sofort aktiv. Zum Anmelden verwendest du künftig diese Adresse.</p>
                <a href="{{ route('homepage.account') }}" class="mt-8 inline-block rounded-lg bg-brand px-4 py-3 font-semibold text-brand-contrast hover:opacity-90">Zu meinem Konto</a>
                @break

            @case(\App\Services\Contacts\EmailChangeService::RESULT_ADDRESS_TAKEN)
                <h1 class="text-3xl font-bold tracking-tight text-brand-heading">Adresse nicht verfügbar</h1>
                <p class="mt-3 text-gray-600">Diese E-Mail-Adresse wird inzwischen schon verwendet. Die Änderung wurde abgebrochen.</p>
                @break

            @default
                @if ($change)
                    <h1 class="text-3xl font-bold tracking-tight text-brand-heading">E-Mail-Adresse ändern</h1>
                    <p class="mt-3 text-gray-600">
                        @if ($change->isWaitingForOldAddress())
                            Bitte bestätige, dass die Adresse deines Kontos auf <strong>{{ $change->new_email }}</strong> geändert werden soll.
                        @else
                            Bitte bestätige, dass <strong>{{ $change->new_email }}</strong> deine neue E-Mail-Adresse ist.
                        @endif
                    </p>
                    <form method="POST" action="{{ route('homepage.email-change.confirm', $token) }}" class="mt-8">
                        @csrf
                        <button type="submit" class="w-full rounded-lg bg-brand px-4 py-3 font-semibold text-brand-contrast hover:opacity-90">Bestätigen</button>
                    </form>
                @else
                    <h1 class="text-3xl font-bold tracking-tight text-brand-heading">Link nicht mehr gültig</h1>
                    <p class="mt-3 text-gray-600">Dieser Link ist abgelaufen, wurde schon verwendet oder die Änderung wurde abgebrochen. Du kannst die Änderung in deinem Konto neu starten.</p>
                    <p class="mt-3 text-sm text-gray-500">Kein Zugriff mehr auf deine bisherige Adresse? <a href="{{ route('homepage.contact-form', ['anliegen' => \App\Enums\ContactFormTopic::LostEmailAccess->value]) }}" class="font-medium text-brand-ink hover:underline">Schreib uns</a>.</p>
                @endif
        @endswitch
    </div>
</x-homepage.layout>
