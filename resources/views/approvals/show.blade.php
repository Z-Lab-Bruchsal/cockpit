@php
    $instance = $registration->courseInstance;
    $firstDate = $instance->dates->first()?->starts_at;
@endphp

<x-homepage.layout title="Freigabe">
    <div class="mx-auto max-w-xl px-4 py-12 sm:py-16">
        @if (session('decided'))
            <div class="rounded-2xl border border-green-200 bg-green-50 p-5 text-green-900" role="status">
                <p class="font-semibold">Erledigt – die Anmeldung wurde {{ session('decided') }}.</p>
                <p class="mt-1 text-sm">Die Familie wurde per E-Mail informiert.</p>
            </div>
        @elseif (! $isOpen)
            <div class="rounded-2xl bg-gray-50 p-5 text-gray-700">
                <p class="font-semibold">Hier ist nichts mehr zu entscheiden.</p>
                <p class="mt-1 text-sm">Die Anfrage wurde bereits bearbeitet oder die Anmeldung wurde storniert.</p>
            </div>
        @else
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ $decision === 'ablehnen' ? 'Anfrage ablehnen' : 'Anmeldung freigeben' }}</h1>
            <p class="mt-3 text-gray-600">
                @if ($registration->awaitsSiblingApproval())
                    {{ $registration->activeEnrollments->count() }} Geschwister möchten an <strong>{{ $instance->displayTitle() }}</strong> teilnehmen, es sind aber nicht mehr genug Plätze frei.
                @else
                    Eine Familie mit {{ $registration->activeEnrollments->count() }} Kindern ist auf der Warteliste für <strong>{{ $instance->displayTitle() }}</strong> an der Reihe, es sind aber nur {{ $instance->freeSeats() }} Plätze frei.
                @endif
            </p>

            <dl class="mt-6 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 rounded-2xl border border-gray-200 bg-white p-5 text-sm">
                <dt class="font-medium text-gray-500">Belegung</dt>
                <dd>{{ $instance->takenSeats() }} von {{ $instance->capacity }}</dd>
                <dt class="font-medium text-gray-500">Kontakt</dt>
                <dd>{{ $registration->displayContactName() }}@if ($registration->phone), {{ $registration->phone }}@endif</dd>
                @foreach ($registration->activeEnrollments as $enrollment)
                    <dt class="font-medium text-gray-500">Kind {{ $loop->iteration }}</dt>
                    <dd>{{ $enrollment->displayKidName() }}@if ($enrollment->kidAge($firstDate) !== null) ({{ $enrollment->kidAge($firstDate) }} Jahre)@endif</dd>
                @endforeach
            </dl>

            <form method="POST" action="{{ $decideUrl }}" class="mt-8">
                @csrf
                <button type="submit" @class([
                    'w-full rounded-lg px-4 py-3 font-semibold text-white',
                    'bg-green-600 hover:bg-green-700' => $decision === 'freigeben',
                    'bg-red-600 hover:bg-red-700' => $decision === 'ablehnen',
                ])>{{ $decision === 'ablehnen' ? 'Jetzt ablehnen' : 'Jetzt freigeben' }}</button>
            </form>
        @endif
    </div>
</x-homepage.layout>
