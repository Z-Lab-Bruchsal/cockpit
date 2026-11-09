@props(['instance', 'bookable' => true])

@php
    /** @var \App\Models\CourseInstance $instance */
    $course = $instance->course;
    $isFeatured = $instance->isFeatured();
    $image = $instance->cardImage();
    $label = $instance->cardLabel();
    $availability = $instance->availability();
    $ageLabel = $course->audienceLabel();
    $facts = collect([$ageLabel ? \Illuminate\Support\Str::ucfirst($ageLabel) : null, $instance->isDropIn() ? 'Ohne Anmeldung' : null])->filter()->implode(' · ');
@endphp

<article
    data-participation="{{ $instance->participation->value }}"
    data-offer-id="{{ $instance->id }}"
    data-design-role="card-bg"
    @if ($instance->exists) data-design-element="offer:{{ $instance->id }}" @endif
    @if ($instance->exists && ($elementStyle = \App\Models\DesignTheme::elementStyle('offer:'.$instance->id))) style="{{ $elementStyle }}" @endif
    {{ $attributes->class([
        'key-card group relative flex flex-col overflow-hidden',
        'sm:col-span-2 lg:col-span-6' => $isFeatured,
        'lg:col-span-4 xl:col-span-3' => ! $isFeatured,
    ]) }}
>
    <div @class([
        'relative shrink-0 overflow-hidden bg-gray-100',
        'aspect-[5/2]' => $isFeatured,
        'aspect-[5/2] sm:aspect-[5/3] lg:aspect-[5/2]' => ! $isFeatured,
    ])>
        @if ($image)
            <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" loading="lazy" class="size-full object-cover transition duration-300 group-hover:scale-105">
        @endif
        <x-homepage.image-label :label="$label" :size="$isFeatured ? 'md' : 'sm'" @class(['top-4 left-4 sm:top-8 sm:left-8' => $isFeatured, 'top-2 left-2' => ! $isFeatured]) />
        <x-homepage.edit-button :url="\App\Services\Homepage\InlineEditing::offerUrl($instance)" label="Angebot bearbeiten" class="absolute top-2 right-2" />
    </div>

    <div @class(['flex grow flex-col gap-1 p-5 sm:p-6', 'bg-offer-featured text-accent-contrast' => $isFeatured]) @if ($isFeatured) data-design-role="offer-featured-bg" @endif>
        <h3 class="text-lg font-bold">{{ $instance->displayTitle() }}</h3>

        @if ($instance->isDropIn())
            @if ($instance->schedule_text)
                <p class="font-light text-gray-600">{{ $instance->schedule_text }}</p>
            @endif
            @if ($instance->schedule_note)
                <p class="text-xs font-light text-gray-500">{{ $instance->schedule_note }}</p>
            @endif
        @elseif ($instance->firstDate)
            <p class="font-light text-gray-600">{{ $instance->firstDate->formattedRange() }}</p>
        @else
            <p class="font-light text-gray-600">Neue Termine folgen</p>
        @endif

        @if ($facts !== '')
            <p class="text-sm font-semibold">{{ $facts }}</p>
        @endif

        <div class="mt-auto flex flex-wrap gap-2 pt-4">
            <a href="{{ $instance->url() }}" class="inline-flex items-center rounded-lg border-2 border-button-ink bg-card px-4 py-2 text-sm font-bold text-button-ink hover:bg-button-ink hover:text-white" data-design-role="button-ink">
                Mehr erfahren<span class="sr-only">: {{ $instance->displayTitle() }}</span>
            </a>
            @if ($bookable && ! $instance->isDropIn() && $availability->acceptsRegistrations() && ! $course->isForSchoolClasses())
                <a href="{{ $instance->url() }}#anmeldung" class="inline-flex items-center rounded-lg bg-button-ink px-4 py-2 text-sm font-bold text-white shadow hover:opacity-90" data-design-role="button-ink">
                    {{ $availability === \App\Enums\CourseAvailability::Waitlist ? 'Warteliste' : 'Kurs buchen' }}<span class="sr-only">: {{ $instance->displayTitle() }}</span>
                </a>
            @endif
        </div>
    </div>
</article>
