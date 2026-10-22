@props(['tiles'])

@if ($tiles->isNotEmpty())
    <div {{ $attributes->class('grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3') }}>
        @foreach ($tiles as $tile)
            <x-homepage.tile :section="$tile" />
        @endforeach
    </div>
@endif
