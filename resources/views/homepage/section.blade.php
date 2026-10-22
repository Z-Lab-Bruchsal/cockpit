<x-homepage.layout :title="$section->title">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
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

        @if ($section->show_title)
            <h1 class="mb-6 text-3xl font-bold tracking-tight text-brand-heading sm:text-4xl">{{ $section->title }}</h1>
        @endif

        @if (! empty($section->content))
            <div class="flex flex-col gap-8">
                @foreach ($section->content as $block)
                    <x-homepage.block :block="$block" />
                @endforeach
            </div>
        @endif

        <x-homepage.tiles :tiles="$tiles" @class(['mt-10' => ! empty($section->content)]) />

        <div class="mt-10">
            <a href="{{ count($ancestors) ? end($ancestors)->url() : route('homepage.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-ink hover:underline">
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.61l4.22 4.22a.75.75 0 1 1-1.06 1.06l-5.5-5.5a.75.75 0 0 1 0-1.06l5.5-5.5a.75.75 0 1 1 1.06 1.06L5.61 9.25h10.64A.75.75 0 0 1 17 10Z" clip-rule="evenodd"/></svg>
                Zurück
            </a>
        </div>
    </div>
</x-homepage.layout>
