<x-homepage.layout title="Warenkorb">
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-12">
        <h1 class="mb-6 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Anmeldung</h1>

        @if (session('status'))
            <p class="mb-6 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</p>
        @endif

        @if (session('booking_limit_warning'))
            <p class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">{{ session('booking_limit_warning') }}</p>
        @endif

        <livewire:homepage.checkout />
    </div>
</x-homepage.layout>
