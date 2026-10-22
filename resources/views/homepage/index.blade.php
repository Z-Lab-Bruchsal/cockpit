@php($settings = \App\Models\CompanySetting::current())

<x-homepage.layout>
    <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 sm:px-6">
        <div class="key-card p-6 sm:p-8">
            <h1 class="text-3xl font-bold tracking-tight text-brand-heading sm:text-5xl">{{ $settings->homepageTitle() }}</h1>
            @if ($settings->homepage_tagline)
                <p class="mt-4 max-w-3xl text-lg leading-relaxed">{{ $settings->homepage_tagline }}</p>
            @endif
        </div>

        @if ($tiles->isEmpty())
            <p class="py-12 text-center text-gray-500">Hier entsteht gerade etwas Neues.</p>
        @else
            <x-homepage.tiles :tiles="$tiles" />
        @endif
    </div>
</x-homepage.layout>
