<div class="flex flex-col gap-4">
    <p class="rounded-xl border border-dashed border-brand-ink/50 bg-white/70 px-4 py-3 text-sm">
        <strong>Kacheln bearbeiten:</strong> Kachel ziehen zum Verschieben, an der Ecke unten rechts ziehen zum Vergrößern
        (oder die Knöpfe nutzen), ✎ zum Bearbeiten. Ausgeblendete Kacheln sind blass dargestellt. Änderungen werden sofort gespeichert.
    </p>

    @if ($this->items->isEmpty())
        <p class="py-12 text-center text-gray-500">Auf dieser Seite gibt es noch keine Kacheln.</p>
    @else
        <div
            wire:sort="sort"
            data-tile-grid
            class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-flow-dense lg:grid-cols-6"
        >
            @foreach ($this->items as $item)
                @php
                    $tile = $item->tile;
                    $uuid = \Illuminate\Support\Js::from($tile->uuid);
                    $widthIndex = array_search($item->width, $widths, true);
                    $label = $tile->label();
                    $isPlaceholder = in_array($tile->type, [\App\Enums\TileType::ContactForm, \App\Enums\TileType::CourseCalendar], true);
                @endphp

                <x-homepage.tile-frame
                    :width="$item->width"
                    :height="$item->height"
                    :card="$item->isCard() || $isPlaceholder"
                    wire:key="tile-{{ $tile->uuid }}"
                    wire:sort:item="{{ $tile->uuid }}"
                    x-data="tileResizer({{ $uuid }}, {{ $item->width }}, {{ $item->height }}, {{ json_encode($widths) }})"
                    x-bind:style="previewStyle"
                    data-tile-width="{{ $item->width }}"
                    data-tile-height="{{ $item->height }}"
                    data-tile="{{ $tile->uuid }}"
                    @class([
                        'relative cursor-grab rounded-2xl outline-2 outline-offset-2 outline-dashed outline-brand-ink/40 active:cursor-grabbing',
                        'opacity-50' => ! $tile->is_visible,
                    ])
                >
                    {{-- Content is not clickable while arranging, except the pencils of offers. --}}
                    <div class="pointer-events-none select-none *:h-full [&_[data-edit-url]]:pointer-events-auto">
                        @if ($isPlaceholder)
                            <div class="flex flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-brand-ink/40 bg-white/80 p-6 text-center">
                                <x-filament::icon :icon="$tile->type->getIcon()" class="size-8 text-ink/60" />
                                <p class="font-bold text-ink">{{ $tile->type->getLabel() }}</p>
                            </div>
                        @else
                            @include('components.homepage.tiles.'.$item->view, [...$item->props, 'item' => $item, 'editing' => true])
                        @endif
                    </div>

                    <div wire:sort:ignore class="absolute top-2 right-2 z-20 flex flex-wrap justify-end gap-1 rounded-lg bg-white/95 p-1 text-xs font-semibold text-ink shadow">
                        <button
                            type="button"
                            class="rounded px-2 py-1 hover:bg-gray-100 disabled:opacity-30"
                            title="Schmaler"
                            aria-label="{{ $label }} schmaler"
                            @disabled($widthIndex === 0)
                            wire:click="resize({{ $uuid }}, {{ $widths[max(0, (int) $widthIndex - 1)] }}, {{ $item->height }})"
                        >−</button>
                        <span class="px-1 py-1 tabular-nums">{{ \App\Models\Tile::WIDTHS[$item->width] ?? '' }}</span>
                        <button
                            type="button"
                            class="rounded px-2 py-1 hover:bg-gray-100 disabled:opacity-30"
                            title="Breiter"
                            aria-label="{{ $label }} breiter"
                            @disabled($widthIndex === count($widths) - 1)
                            wire:click="resize({{ $uuid }}, {{ $widths[min(count($widths) - 1, (int) $widthIndex + 1)] }}, {{ $item->height }})"
                        >+</button>
                        <button
                            type="button"
                            class="rounded px-2 py-1 hover:bg-gray-100"
                            title="{{ $item->height === 2 ? 'Normale Höhe' : 'Doppelt hoch' }}"
                            aria-label="{{ $label }}: {{ $item->height === 2 ? 'normale Höhe' : 'doppelt hoch' }}"
                            wire:click="resize({{ $uuid }}, {{ $item->width }}, {{ $item->height === 2 ? 1 : 2 }})"
                        >{{ $item->height === 2 ? '↥' : '↧' }}</button>
                        <span class="mx-0.5 w-px self-stretch bg-gray-200" aria-hidden="true"></span>
                        <button
                            type="button"
                            class="rounded px-2 py-1 hover:bg-gray-100"
                            title="Bearbeiten"
                            aria-label="{{ $label }} bearbeiten"
                            x-on:click.prevent.stop="$dispatch('open-inline-editor', { url: @js($this->editUrl($tile)) })"
                        >✎</button>
                        <button
                            type="button"
                            class="rounded px-2 py-1 hover:bg-gray-100"
                            title="{{ $tile->is_visible ? 'Ausblenden' : 'Einblenden' }}"
                            aria-label="{{ $label }} {{ $tile->is_visible ? 'ausblenden' : 'einblenden' }}"
                            wire:click="toggleVisibility({{ $uuid }})"
                        >{{ $tile->is_visible ? '◉' : '○' }}</button>
                        <button
                            type="button"
                            class="rounded px-2 py-1 hover:bg-gray-100"
                            title="Duplizieren"
                            aria-label="{{ $label }} duplizieren"
                            wire:click="duplicate({{ $uuid }})"
                        >⧉</button>
                        <button
                            type="button"
                            class="rounded px-2 py-1 text-red-700 hover:bg-red-50"
                            title="Löschen"
                            aria-label="{{ $label }} löschen"
                            wire:click="delete({{ $uuid }})"
                            wire:confirm="Kachel „{{ $label }}“ wirklich löschen?"
                        >✕</button>
                    </div>

                    <div
                        wire:sort:ignore
                        x-on:pointerdown.prevent="start($event)"
                        class="absolute right-0 bottom-0 z-20 hidden size-8 cursor-se-resize items-end justify-end p-1.5 lg:flex"
                        title="Ziehen zum Vergrößern"
                        aria-hidden="true"
                    >
                        <svg class="size-4 text-ink/60" viewBox="0 0 16 16" fill="currentColor"><path d="M14 9v5H9l5-5Zm0-6v2L5 14H3L14 3Z"/></svg>
                    </div>
                </x-homepage.tile-frame>
            @endforeach
        </div>
    @endif

    <details class="group self-start rounded-xl border border-dashed border-brand-ink/50 bg-white/80 text-sm">
        <summary class="cursor-pointer list-none px-4 py-2 font-semibold text-brand-ink">+ Kachel hinzufügen</summary>
        <ul class="grid gap-1 p-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($tileTypes as $type)
                <li>
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left hover:bg-gray-100"
                        x-on:click="$dispatch('open-inline-editor', { url: @js($this->createUrl($type)) })"
                    >
                        <x-filament::icon :icon="$type->getIcon()" class="size-5 shrink-0 text-ink/60" />
                        {{ $type->getLabel() }}
                    </button>
                </li>
            @endforeach
        </ul>
    </details>
</div>
