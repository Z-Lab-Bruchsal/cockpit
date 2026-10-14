@props(['section'])

@php($layout = $section->layout ?? \App\Enums\SectionLayout::Standard)

<a
    href="{{ $section->url() }}"
    @class([
        'group flex flex-col overflow-hidden rounded-2xl border shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
        'border-gray-200 bg-white' => $layout !== \App\Enums\SectionLayout::Highlighted,
        'border-transparent bg-brand text-brand-contrast' => $layout === \App\Enums\SectionLayout::Highlighted,
        'sm:col-span-2 lg:col-span-3 sm:flex-row' => $layout === \App\Enums\SectionLayout::FullWidth,
    ])
>
    @if ($section->tile_image_path)
        <div @class([
            'aspect-[4/3] overflow-hidden bg-gray-100',
            'sm:aspect-auto sm:w-1/3' => $layout === \App\Enums\SectionLayout::FullWidth,
        ])>
            <img src="{{ Storage::disk('public')->url($section->tile_image_path) }}" alt="" loading="lazy" class="size-full object-cover transition duration-300 group-hover:scale-105">
        </div>
    @endif
    <div class="flex grow flex-col gap-2 p-5">
        <h2 class="text-lg font-semibold">{{ $section->title }}</h2>
        @if ($section->teaser)
            <p @class([
                'text-sm',
                'text-gray-600' => $layout !== \App\Enums\SectionLayout::Highlighted,
                'opacity-85' => $layout === \App\Enums\SectionLayout::Highlighted,
            ])>{{ $section->teaser }}</p>
        @endif
        <span @class([
            'mt-auto inline-flex items-center gap-1 pt-2 text-sm font-semibold',
            'text-brand' => $layout !== \App\Enums\SectionLayout::Highlighted,
        ])>
            Mehr erfahren
            <svg class="size-4 transition group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.64l-4.22-4.22a.75.75 0 1 1 1.06-1.06l5.5 5.5a.75.75 0 0 1 0 1.06l-5.5 5.5a.75.75 0 1 1-1.06-1.06l4.22-4.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/></svg>
        </span>
    </div>
</a>
