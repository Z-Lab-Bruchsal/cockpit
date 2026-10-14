@php($settings = \App\Models\CompanySetting::current())

<x-homepage.layout>
    <div class="bg-accent text-accent-contrast">
        <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
            <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">{{ $settings->homepageTitle() }}</h1>
            @if ($settings->homepage_tagline)
                <p class="mt-4 max-w-2xl text-lg opacity-80">{{ $settings->homepage_tagline }}</p>
            @endif
        </div>
    </div>

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
        @if ($tiles->isEmpty())
            <p class="py-12 text-center text-gray-500">Hier entsteht gerade etwas Neues.</p>
        @else
            <x-homepage.tiles :tiles="$tiles" />
        @endif
    </div>
</x-homepage.layout>
