<x-homepage.layout title="Termin buchen">
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-12">
        <h1 class="mb-6 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Termin buchen</h1>

        @if (session('status'))
            <p class="mb-6 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</p>
        @endif

        <x-homepage.hold-countdown :expires-at="$registration->expires_at" :restart-url="route('homepage.schools.booking.show', $token)" formal />

        <livewire:schools.school-booking-form :token="$token" />
    </div>
</x-homepage.layout>
