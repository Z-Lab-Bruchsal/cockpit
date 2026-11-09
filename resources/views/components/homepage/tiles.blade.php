@props(['tiles'])

@if ($tiles->isNotEmpty())
    <div {{ $attributes->class('grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-flow-dense lg:auto-rows-[minmax(13rem,auto)] lg:grid-cols-6') }}>
        @foreach ($tiles as $tile)
            <x-homepage.tile :tile="$tile" />
        @endforeach
    </div>
@endif
