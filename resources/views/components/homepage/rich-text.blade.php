@props(['html'])

@if (filled($html))
    <div {{ $attributes->class('prose max-w-none prose-a:text-brand') }}>
        {{ \Filament\Forms\Components\RichEditor\RichContentRenderer::make($html) }}
    </div>
@endif
