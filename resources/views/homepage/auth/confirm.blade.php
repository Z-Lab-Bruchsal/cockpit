<x-homepage.layout title="Anmelden">
    <div class="mx-auto max-w-md px-4 py-12 text-center sm:py-16">
        @if ($isValid)
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">Fast geschafft</h1>
            <p class="mt-3 text-gray-600">Klicke auf den Button, um dich anzumelden.</p>

            <form method="POST" action="{{ route('homepage.login.consume', $token) }}" class="mt-8">
                @csrf
                <button type="submit" class="w-full rounded-lg bg-brand px-4 py-3 font-semibold text-brand-contrast hover:opacity-90">Jetzt anmelden</button>
            </form>
        @else
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">Link nicht mehr gültig</h1>
            <p class="mt-3 text-gray-600">Dieser Anmeldelink ist abgelaufen oder wurde schon verwendet. Fordere einfach einen neuen an.</p>

            <a href="{{ route('homepage.login') }}" class="mt-8 inline-block rounded-lg bg-brand px-4 py-3 font-semibold text-brand-contrast hover:opacity-90">Neuen Link anfordern</a>
        @endif
    </div>
</x-homepage.layout>
