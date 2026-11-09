<x-homepage.layout :title="$instance->displayTitle()" is-preview>
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <p class="mb-4 text-sm text-gray-500">So erscheint die Karte in Angebotslisten ({{ $instance->isFeatured() ? 'groß' : 'normal' }}):</p>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-12">
            <x-homepage.offer-card :instance="$instance" :bookable="false" />
        </div>
    </div>
</x-homepage.layout>
