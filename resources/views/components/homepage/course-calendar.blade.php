@props(['audience' => null])

<div
    {{ $attributes->class('course-calendar rounded-2xl border border-gray-200 bg-white p-3 sm:p-5') }}
    data-course-calendar
    data-events-url="{{ route('homepage.calendar.events', array_filter(['zielgruppe' => $audience])) }}"
>
    <noscript>Für den Kalender wird JavaScript benötigt. Alle Kurse findest du auch in der <a href="{{ route('homepage.courses.index') }}" class="text-brand-ink underline">Kursübersicht</a>.</noscript>
</div>

<ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600" aria-label="Legende">
    @foreach ([\App\Enums\CourseAvailability::Available, \App\Enums\CourseAvailability::FewSeatsLeft, \App\Enums\CourseAvailability::Waitlist, \App\Enums\CourseAvailability::Full] as $availability)
        <li class="flex items-center gap-1.5"><span class="size-2.5 rounded-full" style="background-color: {{ $availability->hexColor() }}"></span>{{ $availability->getLabel() }}</li>
    @endforeach
</ul>
