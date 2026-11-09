<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-dashed border-white/40 px-4 py-3 text-sm">
        <p class="grow"><strong>Footer anordnen:</strong> Elemente am Griff ⠿ ziehen, Links und Profile innerhalb ihres Elements direkt ziehen. Breite mit −/+ ändern. Änderungen werden sofort gespeichert.</p>
        <button type="button" wire:click="addText" class="rounded-lg bg-white px-3 py-1.5 font-semibold text-ink hover:bg-gray-100">+ Text hinzufügen</button>
    </div>

    <div wire:sort="sort" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-6">
        @foreach ($this->blocks as $block)
            @php
                $id = $block['data']['block_id'];
                $width = (int) ($block['data']['width'] ?? 2);
                $widthIndex = array_search($width, $widths, true);
                $label = \App\Models\CompanySetting::FOOTER_BLOCK_TYPES[$block['type']] ?? 'Element';
            @endphp

            <div
                wire:key="footer-{{ $id }}"
                wire:sort:item="{{ $id }}"
                data-footer-block="{{ $id }}"
                class="{{ \App\Livewire\Homepage\FooterEditor::widthClass($width) }} relative min-h-24 rounded-xl p-3 pt-12 outline-2 outline-offset-2 outline-dashed outline-white/40"
            >
                <div class="absolute top-2 right-2 left-2 flex flex-wrap items-center gap-1 rounded-lg bg-white/90 p-1 text-xs font-semibold text-ink shadow">
                    <span wire:sort:handle class="cursor-grab rounded px-1.5 py-1 text-base leading-none hover:bg-gray-100 active:cursor-grabbing" title="Ziehen zum Verschieben" aria-label="{{ $label }} verschieben">⠿</span>
                    <span class="px-1">{{ $label }}</span>
                    <span class="ml-auto"></span>
                    <button type="button" class="rounded px-2 py-1 hover:bg-gray-100 disabled:opacity-30" aria-label="{{ $label }} schmaler" @disabled($widthIndex === 0) wire:click="resize(@js($id), {{ $widths[max(0, $widthIndex - 1)] }})">−</button>
                    <span class="py-1 tabular-nums">{{ \App\Models\Section::TILE_WIDTHS[$width] ?? '' }}</span>
                    <button type="button" class="rounded px-2 py-1 hover:bg-gray-100 disabled:opacity-30" aria-label="{{ $label }} breiter" @disabled($widthIndex === count($widths) - 1) wire:click="resize(@js($id), {{ $widths[min(count($widths) - 1, $widthIndex + 1)] }})">+</button>
                    @if ($block['type'] === 'text')
                        <x-homepage.edit-button :url="\App\Filament\Pages\Inline\EditFooterBlockInline::getUrl(['block' => $id])" label="Text" class="px-2! py-1!" />
                    @endif
                    <button type="button" class="rounded px-2 py-1 text-red-700 hover:bg-red-50" aria-label="{{ $label }} entfernen" wire:click="remove(@js($id))" wire:confirm="Dieses Element aus dem Footer entfernen?">✕</button>
                </div>

                @if (in_array($block['type'], ['links', 'social'], true))
                    @php
                        $kind = $block['type'];
                        $entries = array_values($settings->{\App\Livewire\Homepage\FooterEditor::LIST_COLUMNS[$kind]} ?? []);
                    @endphp
                    <ul wire:sort="{{ $kind === 'links' ? 'sortLinks' : 'sortSocial' }}" class="grid gap-2" data-footer-entries="{{ $kind }}">
                        @foreach ($entries as $index => $entry)
                            <li
                                wire:key="footer-{{ $kind }}-{{ $index }}-{{ md5(json_encode($entry)) }}"
                                wire:sort:item="{{ $index }}"
                                class="key-button cursor-grab justify-between! gap-2 active:cursor-grabbing"
                            >
                                <span class="truncate">{{ $kind === 'links' ? ($entry['label'] ?? 'Link') : (\App\Models\CompanySetting::SOCIAL_PLATFORMS[$entry['platform'] ?? 'other'] ?? 'Link') }}</span>
                                <span wire:sort:ignore class="flex shrink-0 gap-1">
                                    <x-homepage.edit-button :url="\App\Filament\Pages\Inline\EditFooterLinkInline::getUrl(['kind' => $kind, 'index' => $index])" label="" class="px-1.5! py-1!" aria-label="Bearbeiten" />
                                    <button type="button" class="rounded px-1.5 text-red-700 hover:bg-red-50" aria-label="Entfernen" wire:click="removeEntry(@js($kind), {{ $index }})" wire:confirm="Diesen Eintrag entfernen?">✕</button>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <button type="button" wire:click="addEntry(@js($kind))" class="mt-2 text-xs font-semibold text-footer-ink underline hover:text-footer-hover">+ {{ $kind === 'links' ? 'Link' : 'Profil' }} hinzufügen</button>
                @else
                    <x-homepage.footer.block :block="$block" :settings="$settings" />
                @endif

                @if ($block['type'] === 'text' && blank(strip_tags((string) ($block['data']['body'] ?? ''))))
                    <p class="text-footer-muted italic">Leerer Text – über „Text“ bearbeiten.</p>
                @endif
            </div>
        @endforeach
    </div>
</div>
