<x-homepage.layout title="Kursinfos abbestellen">
    <div class="mx-auto max-w-md px-4 py-12 text-center sm:py-16">
        @if (session('unsubscribed') || ! $contact->wantsInterestMails())
            <h1 class="text-3xl font-bold tracking-tight text-brand-heading">Abgemeldet</h1>
            <p class="mt-3 text-gray-600">Du bekommst keine E-Mails mehr zu neuen Kursen. Bestätigungen zu deinen Anmeldungen schicken wir dir natürlich weiterhin.</p>
            <p class="mt-3 text-sm text-gray-500">Doch wieder Lust auf Kursinfos? Du kannst sie jederzeit in deinem <a href="{{ route('homepage.account') }}" class="font-medium text-brand-ink hover:underline">Konto</a> wieder abonnieren.</p>
        @else
            <h1 class="text-3xl font-bold tracking-tight text-brand-heading">Kursinfos abbestellen?</h1>
            <p class="mt-3 text-gray-600">Du bekommst dann keine E-Mails mehr, wenn neue Kurse für deine Kinder starten.</p>
            <form method="POST" action="{{ route('homepage.unsubscribe.confirm', $token) }}" class="mt-8">
                @csrf
                <button type="submit" class="w-full rounded-lg bg-brand px-4 py-3 font-semibold text-brand-contrast hover:opacity-90">Ja, abbestellen</button>
            </form>
        @endif
    </div>
</x-homepage.layout>
