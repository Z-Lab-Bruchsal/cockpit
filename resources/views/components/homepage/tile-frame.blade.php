@props(['width' => 2, 'height' => 1, 'card' => true])

{{-- One cell of the homepage grid, see TileGrid. Cards get the minimum height of a grid row. --}}
<div {{ $attributes->class([
    'flex min-w-0 flex-col *:grow',
    // Literal classes, so that Tailwind picks them up.
    match ((int) $width) {
        3 => 'lg:col-span-3',
        4 => 'sm:col-span-2 lg:col-span-4',
        6 => 'sm:col-span-2 lg:col-span-6',
        default => 'lg:col-span-2',
    },
    'lg:min-h-52' => $card && (int) $height === 1,
    'lg:row-span-2 lg:min-h-[27.25rem]' => (int) $height === 2,
]) }}>
    {{ $slot }}
</div>
