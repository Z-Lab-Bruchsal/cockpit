<x-homepage.layout title="Buchung">
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-12">
        <h1 class="mb-6 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Buchung</h1>

        <x-homepage.hold-countdown :expires-at="$registration->expires_at" :restart-url="route('homepage.booking.show', $token)" />

        <livewire:homepage.booking-form :token="$token" />
    </div>
</x-homepage.layout>
