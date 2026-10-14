<x-homepage.layout title="Für Schulen">
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-12">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Für Schulklassen</h1>
        <p class="mt-3 text-gray-600">Buchen Sie einen Kurs für Ihre Klasse – je nach Kurs bei uns im Z-LAB oder bei Ihnen an der Schule. Freie Termine finden Sie im Kalender und in der Terminliste. Jede Buchung ist zunächst eine Reservierung, die unsere Kursleitung bestätigt.</p>

        @if (session('school_notice'))
            <p class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">{{ session('school_notice') }}</p>
        @endif

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            <a href="{{ route('homepage.schools.calendar') }}" class="rounded-2xl border border-gray-200 bg-white p-5 hover:border-brand">
                <h2 class="text-lg font-semibold text-gray-900">Kalender</h2>
                <p class="mt-1 text-sm text-gray-600">Alle Termine für Schulklassen auf einen Blick.</p>
            </a>
            <a href="{{ route('homepage.schools.slots') }}" class="rounded-2xl border border-gray-200 bg-white p-5 hover:border-brand">
                <h2 class="text-lg font-semibold text-gray-900">Freie Termine</h2>
                <p class="mt-1 text-sm text-gray-600">Liste aller buchbaren Termine, nach Kurs sortiert.</p>
            </a>
        </div>

        <section class="mt-10 rounded-2xl bg-gray-50 p-6" aria-labelledby="zugang">
            <h2 id="zugang" class="text-xl font-semibold text-gray-900">Zugang für Schulen</h2>

            @if ($contact?->canBookForSchool())
                <p class="mt-2 text-gray-600">Sie sind als {{ $contact->isSchool() ? 'Schulkonto' : 'Lehrkraft der '.$contact->school?->name }} angemeldet.</p>
                <a href="{{ route('homepage.schools.account') }}" class="mt-4 inline-block rounded-lg bg-brand px-4 py-2 font-semibold text-brand-contrast hover:opacity-90">Zum Schulkonto</a>
            @else
                <ol class="mt-3 list-decimal space-y-1 pl-5 text-gray-600">
                    <li>Die Schule registriert sich einmal mit ihrer <strong>Funktionsadresse</strong> (z. B. sekretariat@…) und ihrer Anschrift.</li>
                    <li>Im Schulkonto lädt die Schule ihre <strong>Lehrkräfte</strong> ein. Jede Lehrkraft bekommt ein eigenes Unterkonto.</li>
                    <li>Angemeldet können Sie freie Termine in den Warenkorb legen und für Ihre Klasse anfragen.</li>
                </ol>
                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ route('homepage.schools.register') }}" class="rounded-lg bg-brand px-4 py-2 font-semibold text-brand-contrast hover:opacity-90">Schule registrieren</a>
                    <a href="{{ route('homepage.login', ['weiter' => route('homepage.schools.account', absolute: false)]) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 font-semibold hover:bg-gray-100">Anmelden</a>
                </div>
            @endif
        </section>
    </div>
</x-homepage.layout>
