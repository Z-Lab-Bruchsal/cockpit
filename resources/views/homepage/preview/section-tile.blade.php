<x-homepage.layout :title="$section->title" is-preview>
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <p class="mb-4 text-sm text-gray-500">So erscheint eine Kachel, die auf diesen Bereich verlinkt (Überschrift, Text und Bild lassen sich pro Kachel überschreiben):</p>
        @php
            $card = \App\Services\Homepage\TileCard::forSection($section);
        @endphp
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-6">
            <x-homepage.tile-frame :width="$card->width" :height="$card->height">
                <x-homepage.tile :tile="$card" />
            </x-homepage.tile-frame>
        </div>
    </div>
</x-homepage.layout>
