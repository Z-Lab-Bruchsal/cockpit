@php
    $isHome = (bool) $section->is_home;
@endphp

<x-homepage.layout :title="$isHome ? null : $section->title" :is-preview="$preview ?? false">
    <div @class(['mx-auto max-w-6xl px-4 sm:px-6', 'py-8 sm:py-12' => ! $isHome])>
        @unless ($isHome)
            <nav aria-label="Brotkrümel" class="mb-6 text-sm text-gray-500">
                <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <li><a href="{{ route('homepage.index') }}" class="hover:text-gray-900 hover:underline">Start</a></li>
                    @foreach ($ancestors as $ancestor)
                        <li aria-hidden="true">/</li>
                        <li><a href="{{ $ancestor->url() }}" class="hover:text-gray-900 hover:underline">{{ $ancestor->title }}</a></li>
                    @endforeach
                    <li aria-hidden="true">/</li>
                    <li aria-current="page" class="font-medium text-gray-900">{{ $section->title }}</li>
                </ol>
            </nav>
        @endunless

        @if ($section->show_title && ! $isHome)
            <h1 class="mb-6 text-3xl font-bold tracking-tight text-brand-heading sm:text-4xl">{{ $section->title }}</h1>
        @else
            <h1 class="sr-only">{{ $isHome ? \App\Models\CompanySetting::current()->homepageTitle() : $section->title }}</h1>
        @endif

        @php
            $isArranging = $section->exists
                && \App\Services\Homepage\InlineEditing::isActive()
                && \App\Livewire\Homepage\SectionTileEditor::canArrange($section);
            $items = $isArranging ? collect() : \App\Services\Homepage\TileGrid::items($section);
        @endphp

        @if ($isArranging)
            <livewire:homepage.section-tile-editor :section-id="$section->id" :key="'section-tile-editor-'.$section->id" />
        @elseif ($items->isNotEmpty())
            <x-homepage.tile-grid :items="$items" />
        @elseif ($isHome)
            <p class="py-12 text-center text-gray-500">Hier entsteht gerade etwas Neues.</p>
        @endif

        @unless ($isHome)
            <div class="mt-10">
                <a href="{{ count($ancestors) ? end($ancestors)->url() : route('homepage.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-ink hover:underline">
                    <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.61l4.22 4.22a.75.75 0 1 1-1.06 1.06l-5.5-5.5a.75.75 0 0 1 0-1.06l5.5-5.5a.75.75 0 1 1 1.06 1.06L5.61 9.25h10.64A.75.75 0 0 1 17 10Z" clip-rule="evenodd"/></svg>
                    Zurück
                </a>
            </div>
        @endunless
    </div>
</x-homepage.layout>
