<div class="flex flex-col gap-4">
    <p class="rounded-xl border border-dashed border-brand-ink/50 bg-white/70 px-4 py-3 text-sm">
        <strong>Kacheln anordnen:</strong> Kachel ziehen zum Verschieben, an der Ecke unten rechts ziehen zum Vergrößern
        (oder die Knöpfe nutzen). Änderungen werden sofort gespeichert.
    </p>

    @if ($this->tiles->isEmpty())
        <p class="py-12 text-center text-gray-500">Auf dieser Ebene gibt es keine sichtbaren Kacheln.</p>
    @else
        <div
            wire:sort="sort"
            data-tile-grid
            class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-flow-dense lg:auto-rows-[minmax(13rem,auto)] lg:grid-cols-6"
        >
            @foreach ($this->tiles as $tile)
                @php
                    $widthIndex = array_search($tile->tile_width, $widths, true);
                    $height = $tile->tile_height ?: 1;
                @endphp

                <x-homepage.tile
                    :section="$tile"
                    editing
                    wire:key="tile-{{ $tile->id }}"
                    wire:sort:item="{{ $tile->id }}"
                    x-data="tileResizer({{ $tile->id }}, {{ (int) $tile->tile_width }}, {{ (int) $height }}, {{ json_encode($widths) }})"
                    x-bind:style="previewStyle"
                    data-tile-width="{{ (int) $tile->tile_width }}"
                    data-tile-height="{{ (int) $height }}"
                    class="relative cursor-grab outline-2 outline-offset-2 outline-dashed outline-brand-ink/40 active:cursor-grabbing"
                >
                    <div wire:sort:ignore class="absolute top-2 right-2 flex gap-1 rounded-lg bg-white/90 p-1 text-xs font-semibold text-ink shadow">
                        <button
                            type="button"
                            class="rounded px-2 py-1 hover:bg-gray-100 disabled:opacity-30"
                            title="Schmaler"
                            aria-label="{{ $tile->title }} schmaler"
                            @disabled($widthIndex === 0)
                            wire:click="resize({{ $tile->id }}, {{ $widths[max(0, $widthIndex - 1)] }}, {{ $height }})"
                        >−</button>
                        <span class="px-1 py-1 tabular-nums">{{ \App\Models\Section::TILE_WIDTHS[$tile->tile_width] ?? '' }}</span>
                        <button
                            type="button"
                            class="rounded px-2 py-1 hover:bg-gray-100 disabled:opacity-30"
                            title="Breiter"
                            aria-label="{{ $tile->title }} breiter"
                            @disabled($widthIndex === count($widths) - 1)
                            wire:click="resize({{ $tile->id }}, {{ $widths[min(count($widths) - 1, $widthIndex + 1)] }}, {{ $height }})"
                        >+</button>
                        <button
                            type="button"
                            class="rounded px-2 py-1 hover:bg-gray-100"
                            title="{{ $height === 2 ? 'Normale Höhe' : 'Doppelt hoch' }}"
                            aria-label="{{ $tile->title }}: {{ $height === 2 ? 'normale Höhe' : 'doppelt hoch' }}"
                            wire:click="resize({{ $tile->id }}, {{ $tile->tile_width }}, {{ $height === 2 ? 1 : 2 }})"
                        >{{ $height === 2 ? '↥' : '↧' }}</button>
                    </div>

                    <div
                        wire:sort:ignore
                        x-on:pointerdown.prevent="start($event)"
                        class="absolute right-0 bottom-0 hidden size-8 cursor-se-resize items-end justify-end p-1.5 lg:flex"
                        title="Ziehen zum Vergrößern"
                        aria-hidden="true"
                    >
                        <svg class="size-4 text-ink/60" viewBox="0 0 16 16" fill="currentColor"><path d="M14 9v5H9l5-5Zm0-6v2L5 14H3L14 3Z"/></svg>
                    </div>
                </x-homepage.tile>
            @endforeach
        </div>
    @endif
</div>
