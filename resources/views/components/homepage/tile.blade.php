@props(['section'])

@php($layout = $section->layout ?? \App\Enums\SectionLayout::Standard)

<a
    href="{{ $section->url() }}"
    @class([
        'key-card key-card-interactive group flex flex-col overflow-hidden focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-ink',
        'bg-brand! text-brand-contrast hover:border-transparent' => $layout === \App\Enums\SectionLayout::Highlighted,
        'sm:col-span-2 lg:col-span-3 sm:flex-row' => $layout === \App\Enums\SectionLayout::FullWidth,
    ])
>
    @if ($section->tile_image_path)
        <div @class([
            'aspect-[4/3] overflow-hidden bg-gray-100',
            'sm:aspect-auto sm:w-1/3' => $layout === \App\Enums\SectionLayout::FullWidth,
        ])>
            <img src="{{ Storage::disk('public')->url($section->tile_image_path) }}" alt="{{ $section->tile_image_alt }}" loading="lazy" class="size-full object-cover transition duration-300 group-hover:scale-105">
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
</a>
