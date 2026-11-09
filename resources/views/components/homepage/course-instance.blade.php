@props(['instance', 'template', 'bookable' => true])

@php($availability = $instance->availability())

<article id="durchfuehrung-{{ $instance->id }}" class="key-card p-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">{{ $instance->title ?: ($instance->dates->count() > 1 ? $instance->dates->count().' Termine' : 'Termin') }}</h3>
            @if ($instance->location)
                <p class="text-sm text-gray-600">{{ $instance->location }}</p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-homepage.availability-badge :availability="$availability" />
            @if ($template->show_free_seats && in_array($availability, [\App\Enums\CourseAvailability::Available, \App\Enums\CourseAvailability::FewSeatsLeft], true))
                <span class="text-xs text-gray-600">{{ $instance->freeSeats() }} von {{ $instance->capacity }} Plätzen frei</span>
            @endif
        </div>
    </div>

    @if ($instance->isDropIn())
        <p class="mt-4 border-y border-gray-100 py-2 text-sm">
            <span class="font-medium text-gray-800">{{ $instance->schedule_text }}</span>
            @if ($instance->schedule_note)
                <span class="block text-gray-500">{{ $instance->schedule_note }}</span>
            @endif
        </p>
        <p class="mt-4 text-sm text-gray-600">Keine Anmeldung nötig – einfach vorbeikommen.</p>
    @else
    <ul class="mt-4 divide-y divide-gray-100 border-y border-gray-100 text-sm">
        @foreach ($instance->dates as $date)
            <li class="flex flex-col gap-0.5 py-2 sm:flex-row sm:justify-between">
                <span class="font-medium text-gray-800">{{ $date->formattedRange() }}</span>
                @if ($date->location)
                    <span class="text-gray-500">{{ $date->location }}</span>
                @endif
            </li>
        @endforeach
    </ul>

    <div class="mt-4 text-sm text-gray-600">
        @if (! $bookable)
            <span class="italic">Die Anmeldung ist in der Vorschau deaktiviert.</span>
        @elseif ($availability === \App\Enums\CourseAvailability::NotYetOpen)
            Anmeldung ab {{ $instance->registration_opens_at->locale('de')->isoFormat('dd, DD.MM.YYYY, HH:mm') }} Uhr möglich.
        @else
            <x-homepage.course-registration-form :instance="$instance" :availability="$availability" />
        @endif
    </div>
    @endif

    @if ($bookable && $instance->exists && $instance->course?->slug)
        <a href="{{ $instance->url() }}" class="mt-3 inline-block text-sm font-semibold text-brand-ink hover:underline">Details zu diesem Termin</a>
    @endif
</article>
