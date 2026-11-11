@props(['tile', 'editing' => false])

@php
    /** @var \App\Services\Homepage\TileCard $tile */
    $isHero = $tile->style === \App\Enums\TileStyle::Hero;
    $isHighlighted = $tile->style === \App\Enums\TileStyle::Highlighted;
    $isHorizontal = $tile->width >= 4 && $tile->height === 1 && ! $isHero;
    $isLink = ! $editing && $tile->url !== null;
    $tag = $isLink ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($isLink) href="{{ $tile->url }}" @endif
    data-tile-id="{{ $tile->id }}"
    data-design-element="tile:{{ $tile->id }}"
    data-design-role="{{ $isHighlighted ? 'tile-highlight' : 'card-bg' }}"
    @if ($elementStyle = \App\Models\DesignTheme::elementStyle('tile:'.$tile->id)) style="{{ $elementStyle }}" @endif
    {{ $attributes->class([
        'key-card group flex flex-col overflow-hidden',
        'key-card-interactive focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-ink' => $isLink,
        'bg-tile-highlight! text-brand-contrast hover:border-transparent' => $isHighlighted,
        'sm:flex-row' => $isHorizontal || ($isHero && $tile->imagePath),
    ]) }}
>
    @if ($tile->imagePath)
        <div @class([
            'shrink-0 overflow-hidden bg-gray-100',
            'aspect-[4/3]' => ! $isHorizontal && ! $isHero && $tile->height === 1,
            'aspect-[4/3] lg:aspect-auto lg:min-h-0 lg:grow' => ! $isHorizontal && ! $isHero && $tile->height === 2,
            'aspect-[4/3] sm:aspect-auto sm:w-1/3' => $isHorizontal || $isHero,
            'sm:order-last' => $isHero,
        ])>
            <img src="{{ \App\Services\Homepage\HomepageImage::url($tile->imagePath) }}" alt="{{ $tile->imageAlt }}" loading="lazy" @class(['size-full object-cover transition duration-300', 'group-hover:scale-105' => $isLink]) draggable="false">
        </div>
    @endif
    <div @class(['flex grow flex-col', 'gap-3 p-6 sm:p-7' => ! $isHero, 'gap-4 p-6 sm:p-8' => $isHero])>
        @if ($tile->eyebrow)
            <p @class(['text-sm font-bold', 'text-hero-eyebrow' => ! $isHighlighted]) data-design-role="hero-eyebrow">{{ $tile->eyebrow }}</p>
        @endif
        @if ($tile->heading !== '')
            <h2 @class([
                'font-bold',
                'text-xl sm:text-2xl' => ! $isHero,
                'text-3xl tracking-tight sm:text-5xl' => $isHero,
                'text-tile-heading' => ! $isHighlighted && ! $isHero,
                'text-hero-heading' => ! $isHighlighted && $isHero,
            ]) data-design-role="{{ $isHero ? 'hero-heading' : 'tile-heading' }}">{{ $tile->heading }}</h2>
        @endif
        @if ($tile->textHtml)
            <div @class(['leading-relaxed [&_p+p]:mt-1 [&_strong]:font-semibold', 'max-w-3xl text-lg' => $isHero])>
                {{ \Filament\Forms\Components\RichEditor\RichContentRenderer::make($tile->textHtml) }}
            </div>
        @endif
    </div>
    {{ $slot }}
</{{ $tag }}>
