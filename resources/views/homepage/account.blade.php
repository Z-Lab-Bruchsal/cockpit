<x-homepage.layout title="Mein Konto">
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-12">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Mein Konto</h1>
            <form method="POST" action="{{ route('homepage.logout') }}">
                @csrf
                <button type="submit" class="text-sm font-semibold text-gray-600 hover:text-gray-900 hover:underline">Abmelden</button>
            </form>
        </div>

        @if (session('status'))
            <p class="mb-6 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</p>
        @endif

        <livewire:homepage.account />
    </div>
</x-homepage.layout>
