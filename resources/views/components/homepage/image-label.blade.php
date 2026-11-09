@props(['label', 'size' => 'md', 'floating' => true])

{{-- Sign on a course image, e.g. „Löten lernen. Selber machen.“ – real text instead of text baked into the image. --}}
@if ($label)
    <div {{ $attributes->class(['pointer-events-none flex flex-col items-start gap-2', 'absolute' => $floating, 'p-2' => ! $floating]) }} aria-hidden="{{ $floating ? 'true' : 'false' }}">
        <span @class([
            'image-label -rotate-3 font-serif leading-[1.05] font-bold',
            'pointer-events-auto' => request()->boolean('design'),
            'px-3 py-1.5 text-lg sm:text-xl' => $size === 'sm',
            'px-4 py-2 text-2xl sm:text-4xl' => $size === 'md',
            'px-5 py-3 text-3xl sm:text-5xl' => $size === 'lg',
        ]) data-design-role="label-border">{{ $label['main'] }}</span>
        @if ($label['sub'])
            <span @class([
                'image-label -rotate-2 font-serif font-bold',
                'px-2 py-0.5 text-xs sm:text-sm' => $size === 'sm',
                'ml-2 px-3 py-1 text-base sm:text-xl' => $size !== 'sm',
            ])>{{ $label['sub'] }}</span>
        @endif
    </div>
@endif
