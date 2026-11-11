@props(['items'])

{{-- The grid of a section page: 1 column on phones, 2 on tablets, 6 on desktops. --}}
@if ($items->isNotEmpty())
    <div {{ $attributes->class('grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-flow-dense lg:grid-cols-6') }}>
        @php
            $seenTiles = [];
        @endphp
        @foreach ($items as $item)
            @php
                $isFirstOfTile = ! isset($seenTiles[$item->tile->uuid]);
                $seenTiles[$item->tile->uuid] = true;
                $editUrl = \App\Services\Homepage\InlineEditing::tileUrl($item->tile);
                $anchor = $item->view === 'block' && $item->tile->type !== \App\Enums\TileType::Button
                    ? \Illuminate\Support\Str::slug((string) $item->tile->setting('anchor'))
                    : '';
            @endphp

            <x-homepage.tile-frame
                :width="$item->width"
                :height="$item->height"
                :card="$item->isCard()"
                :id="$anchor !== '' ? $anchor : null"
                data-tile="{{ $item->tile->uuid }}"
                @class([
                    'scroll-mt-24' => $anchor !== '',
                    'relative rounded-2xl outline-2 outline-offset-4 outline-dashed outline-brand-ink/30' => $editUrl,
                ])
            >
                @if ($editUrl && $isFirstOfTile)
                    <x-homepage.edit-button :url="$editUrl" :label="$item->tile->type === \App\Enums\TileType::Courses ? 'Kurs-Kachel bearbeiten' : 'Kachel bearbeiten'" class="absolute -top-3 right-2" />
                @endif
                @include('components.homepage.tiles.'.$item->view, [...$item->props, 'item' => $item])
            </x-homepage.tile-frame>
        @endforeach
    </div>
@endif
