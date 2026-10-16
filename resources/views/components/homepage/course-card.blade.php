@props(['course'])

@php
    $template = $course->effectiveTemplate();
    $style = $template->card_style ?? \App\Enums\CourseCardStyle::ImageTop;
    $nextInstance = $course->upcomingInstances->first();
    $image = $style === \App\Enums\CourseCardStyle::Compact ? null : $course->cardImage();
@endphp

<article
    style="{{ $template->accentStyle() }}"
    @class([
        'group relative flex overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md',
        'flex-col' => $style !== \App\Enums\CourseCardStyle::ImageLeft,
        'flex-col sm:flex-row' => $style === \App\Enums\CourseCardStyle::ImageLeft,
        'border-l-4 border-l-brand' => $style === \App\Enums\CourseCardStyle::Compact,
    ])
>
    @if ($image)
        <div @class([
            'shrink-0 overflow-hidden bg-gray-100',
            'aspect-[4/3]' => $style === \App\Enums\CourseCardStyle::ImageTop,
            'aspect-[4/3] sm:aspect-auto sm:w-2/5' => $style === \App\Enums\CourseCardStyle::ImageLeft,
        ])>
            <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" loading="lazy" class="size-full object-cover transition duration-300 group-hover:scale-105">
        </div>
    @endif

    <div class="flex grow flex-col gap-2 p-5">
        <div class="flex flex-wrap gap-1.5 text-xs">
            @if ($template->show_age_range && $course->audienceLabel())
                <span class="rounded-full bg-gray-100 px-2 py-0.5 font-medium text-gray-700">{{ $course->audienceLabel() }}</span>
            @endif
            @if ($template->show_interests)
                @foreach ($course->interests as $interest)
                    <x-homepage.interest-badge :interest="$interest" />
                @endforeach
            @endif
        </div>

        <h3 class="text-lg font-semibold text-gray-900">{{ $course->name }}</h3>

        @if ($course->teaser)
            <p class="text-sm text-gray-600">{{ $course->teaser }}</p>
        @endif

        <p class="text-sm text-gray-700">
            @if ($nextInstance?->firstDate)
                <span class="font-medium">Nächster Termin:</span> {{ $nextInstance->firstDate->formattedRange() }}
            @else
                <span class="text-gray-500">Neue Termine folgen</span>
            @endif
        </p>

        <a href="{{ $course->url() }}" class="mt-auto inline-flex items-center gap-1 pt-2 text-sm font-semibold text-brand after:absolute after:inset-0">
            Mehr Infos
            <svg class="size-4 transition group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.64l-4.22-4.22a.75.75 0 1 1 1.06-1.06l5.5 5.5a.75.75 0 0 1 0 1.06l-5.5 5.5a.75.75 0 1 1-1.06-1.06l4.22-4.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/></svg>
        </a>
    </div>
</article>
