@props(['section', 'editing' => false])

@php
    $layout = $section->layout ?? \App\Enums\SectionLayout::Standard;
    $width = (int) ($section->tile_width ?: 2);
    $height = (int) ($section->tile_height ?: 1);
    $isHorizontal = $width >= 4 && $height === 1;
    $tag = $editing ? 'div' : 'a';
@endphp

<{{ $tag }}
    @unless ($editing) href="{{ $section->url() }}" @endunless
    {{ $attributes->class([
        'key-card group flex flex-col overflow-hidden',
        'key-card-interactive focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-ink' => ! $editing,
        'bg-brand! text-brand-contrast hover:border-transparent' => $layout === \App\Enums\SectionLayout::Highlighted,
        'sm:flex-row' => $isHorizontal,
        // Literal classes, so that Tailwind picks them up.
        match ($width) {
            3 => 'lg:col-span-3',
            4 => 'sm:col-span-2 lg:col-span-4',
            6 => 'sm:col-span-2 lg:col-span-6',
            default => 'lg:col-span-2',
        },
        'lg:row-span-2' => $height === 2,
    ]) }}
>
    @if ($section->tile_image_path)
        <div @class([
            'shrink-0 overflow-hidden bg-gray-100',
            'aspect-[4/3]' => ! $isHorizontal && $height === 1,
            'aspect-[4/3] lg:aspect-auto lg:min-h-0 lg:grow' => ! $isHorizontal && $height === 2,
            'aspect-[4/3] sm:aspect-auto sm:w-1/3' => $isHorizontal,
        ])>
            <img src="{{ \App\Services\Homepage\HomepageImage::url($section->tile_image_path) }}" alt="{{ $section->tile_image_alt }}" loading="lazy" @class(['size-full object-cover transition duration-300', 'group-hover:scale-105' => ! $editing]) draggable="false">
        </div>
    @endif
    <div class="flex grow flex-col gap-3 p-6 sm:p-7">
        <h2 @class([
            'text-xl font-bold sm:text-2xl',
            'text-brand-heading' => $layout !== \App\Enums\SectionLayout::Highlighted,
        ])>{{ $section->title }}</h2>
        @if ($section->teaser)
            <p class="leading-relaxed">{{ $section->teaser }}</p>
        @endif
    </div>
    {{ $slot }}
</{{ $tag }}>
