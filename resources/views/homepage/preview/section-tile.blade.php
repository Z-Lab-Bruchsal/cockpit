<x-homepage.layout :title="$section->title" is-preview>
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <p class="mb-4 text-sm text-gray-500">So erscheint eine Kachel, die auf diesen Bereich verlinkt (Überschrift, Text und Bild lassen sich pro Kachel überschreiben):</p>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:auto-rows-[minmax(13rem,auto)] lg:grid-cols-6">
            <x-homepage.tile :tile="\App\Services\Homepage\Tile::forSection($section)" />
        </div>
    </div>
</x-homepage.layout>
