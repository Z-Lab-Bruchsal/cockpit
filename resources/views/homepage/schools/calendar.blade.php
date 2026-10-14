<x-homepage.layout title="Kalender für Schulen">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
        <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Termine für Schulklassen</h1>
            <a href="{{ route('homepage.schools.slots') }}" class="text-sm font-semibold text-brand hover:underline">Als Liste anzeigen</a>
        </div>

        <div
            class="course-calendar rounded-2xl border border-gray-200 bg-white p-3 sm:p-5"
            data-course-calendar
            data-events-url="{{ route('homepage.schools.calendar.events') }}"
        >
            <noscript>Für den Kalender wird JavaScript benötigt. Alle Termine finden Sie auch in der <a href="{{ route('homepage.schools.slots') }}" class="text-brand underline">Terminliste</a>.</noscript>
        </div>

        <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600" aria-label="Legende">
            @foreach ([\App\Enums\SchoolSlotState::Free, \App\Enums\SchoolSlotState::InCart, \App\Enums\SchoolSlotState::Requested, \App\Enums\SchoolSlotState::Reserved] as $state)
                <li class="flex items-center gap-1.5"><span class="size-2.5 rounded-full" style="background-color: {{ $state->hexColor() }}"></span>{{ $state->getLabel() }}</li>
            @endforeach
        </ul>
        <p class="mt-2 text-sm text-gray-500">Klicken Sie auf einen Termin, um ihn zu buchen.</p>
    </div>
</x-homepage.layout>
