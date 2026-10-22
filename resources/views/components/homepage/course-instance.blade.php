@props(['instance', 'template'])

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
        @if ($availability === \App\Enums\CourseAvailability::NotYetOpen)
            Anmeldung ab {{ $instance->registration_opens_at->locale('de')->isoFormat('dd, DD.MM.YYYY, HH:mm') }} Uhr möglich.
        @else
            <x-homepage.course-registration-form :instance="$instance" :availability="$availability" />
        @endif
    </div>
</article>
