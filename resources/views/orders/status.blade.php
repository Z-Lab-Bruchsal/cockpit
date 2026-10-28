@php
    $label = $status === 'genommen' ? 'Genommen' : 'Bestellt';
@endphp

<x-homepage.layout title="Bestellung">
    <div class="mx-auto max-w-xl px-4 py-12 sm:py-16">
        @if (session('updated'))
            <div class="rounded-2xl border border-green-200 bg-green-50 p-5 text-green-900" role="status">
                <p class="font-semibold">Erledigt – die Bestellung wurde auf „{{ $label }}" gesetzt.</p>
                <p class="mt-1 text-sm">Du kannst das Fenster schließen oder <a href="{{ $cockpitUrl }}" class="underline">hier</a> zum Cockpit.</p>
            </div>
        @elseif (! $isOpen)
            <div class="rounded-2xl bg-gray-50 p-5 text-gray-700">
                <p class="font-semibold">Hier ist nichts mehr zu tun.</p>
                <p class="mt-1 text-sm">Die Bestellung ist bereits weiter. <a href="{{ $cockpitUrl }}" class="underline">Zum Cockpit</a></p>
            </div>
        @else
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">Bestellung auf „{{ $label }}" setzen</h1>

            <dl class="mt-6 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 rounded-2xl border border-gray-200 bg-white p-5 text-sm">
                <dt class="font-medium text-gray-500">Produkt</dt>
                <dd>{{ $order->name }}</dd>
                <dt class="font-medium text-gray-500">Anzahl</dt>
                <dd>{{ $order->count }}</dd>
            </dl>

            <form method="POST" action="{{ $updateUrl }}" class="mt-8">
                @csrf
                <button type="submit" class="w-full rounded-lg bg-green-600 px-4 py-3 font-semibold text-white hover:bg-green-700">Jetzt auf „{{ $label }}" setzen</button>
            </form>
        @endif
    </div>
</x-homepage.layout>
