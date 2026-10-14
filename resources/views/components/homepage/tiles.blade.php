@props(['tiles'])

@if ($tiles->isNotEmpty())
    <div {{ $attributes->class('grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3') }}>
        @foreach ($tiles as $tile)
            <x-homepage.tile :section="$tile" />
        @endforeach
    </div>
@endif
