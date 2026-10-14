<x-homepage.layout title="Freie Termine für Schulen">
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-12">
        <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Termine für Schulklassen</h1>
            <a href="{{ route('homepage.schools.calendar') }}" class="text-sm font-semibold text-brand hover:underline">Im Kalender anzeigen</a>
        </div>

        @if (session('school_error'))
            <p class="mb-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ session('school_error') }}</p>
        @endif

        @unless ($contact?->canBookForSchool())
            <p class="mb-6 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                Zum Buchen brauchen Sie ein Schulkonto.
                <a href="{{ route('homepage.login', ['weiter' => route('homepage.schools.slots', absolute: false)]) }}" class="font-semibold text-brand hover:underline">Anmelden</a> oder
                <a href="{{ route('homepage.schools.register') }}" class="font-semibold text-brand hover:underline">Schule registrieren</a>.
            </p>
        @endunless

        @forelse ($courses as $instances)
            @php($course = $instances->first()->course)
            <section class="mb-8" aria-labelledby="kurs-{{ $course->id }}">
                <h2 id="kurs-{{ $course->id }}" class="text-xl font-semibold text-gray-900">{{ $course->name }}</h2>
                <p class="text-sm text-gray-600">{{ $course->effectiveSchoolFormat()->getLabel() }}@if ($course->teaser) · {{ $course->teaser }}@endif</p>

                <ul class="mt-3 flex flex-col gap-2">
                    @foreach ($instances as $instance)
                        @php($state = \App\Enums\SchoolSlotState::for($instance))
                        <li id="termin-{{ $instance->id }}" class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 target:border-brand target:ring-2 target:ring-brand/30 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-medium text-gray-900">{{ $instance->dates->first()?->formattedRange() }}</p>
                                @if ($instance->dates->count() > 1)
                                    <p class="text-sm text-gray-600">und {{ $instance->dates->count() - 1 }} weitere Termine</p>
                                @endif
                                @if ($instance->title)
                                    <p class="text-sm text-gray-600">{{ $instance->title }}</p>
                                @endif
                                <p class="mt-1 inline-flex items-center gap-1.5 text-xs font-semibold text-gray-700"><span class="size-2.5 rounded-full" style="background-color: {{ $state->hexColor() }}"></span>{{ $state->getLabel() }}</p>
                            </div>
                            @if ($state === \App\Enums\SchoolSlotState::Free && $contact?->canBookForSchool())
                                <form method="POST" action="{{ route('homepage.schools.cart.add', $instance) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-brand-contrast hover:opacity-90">In den Warenkorb</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <div class="rounded-2xl bg-gray-50 px-6 py-12 text-center text-gray-600">Zurzeit sind keine Termine für Schulklassen geplant.</div>
        @endforelse
    </div>
</x-homepage.layout>
