<x-homepage.layout title="News">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
        <div class="mb-8 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-brand-heading sm:text-4xl">News</h1>
                <p class="mt-2 text-gray-600">Neuigkeiten aus dem Z-LAB.</p>
            </div>
            <a href="{{ route('feeds.main') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-ink hover:underline">
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3.75 3a.75.75 0 0 0-.75.75v.5c0 .414.336.75.75.75H4c6.075 0 11 4.925 11 11v.25c0 .414.336.75.75.75h.5a.75.75 0 0 0 .75-.75V16C17 8.82 11.18 3 4 3h-.25Z"/><path d="M3 8.75A.75.75 0 0 1 3.75 8H4a8 8 0 0 1 8 8v.25a.75.75 0 0 1-.75.75h-.5a.75.75 0 0 1-.75-.75V16a6 6 0 0 0-6-6h-.25A.75.75 0 0 1 3 9.25v-.5ZM7 15a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/></svg>
                News als RSS-Feed abonnieren
            </a>
        </div>

        @if ($posts->isEmpty())
            <p class="text-gray-600">Aktuell gibt es noch keine Neuigkeiten.</p>
        @else
            <div class="flex flex-col gap-6">
                @foreach ($posts as $post)
                    <x-homepage.news-card :post="$post" />
                @endforeach
            </div>

            <div class="mt-10">
                {{ $posts->links('components.homepage.pagination') }}
            </div>
        @endif
    </div>
</x-homepage.layout>
