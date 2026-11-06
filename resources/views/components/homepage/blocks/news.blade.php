@php
    $posts = \App\Models\NewsPost::query()
        ->published()
        ->limit(max(1, min(12, (int) ($data['limit'] ?? 3))))
        ->get();
@endphp

<div class="flex flex-col gap-4">
    @if ($posts->isEmpty())
        <p class="text-gray-600">Aktuell gibt es noch keine Neuigkeiten.</p>
    @else
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($posts as $post)
                <x-homepage.news-card :post="$post" compact />
            @endforeach
        </div>
    @endif

    @if ($data['show_all_link'] ?? true)
        <a href="{{ route('homepage.news.index') }}" class="inline-flex items-center gap-1 self-start text-sm font-semibold text-brand-ink hover:underline">
            Alle News ansehen
            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.64l-4.22-4.22a.75.75 0 1 1 1.06-1.06l5.5 5.5a.75.75 0 0 1 0 1.06l-5.5 5.5a.75.75 0 1 1-1.06-1.06l4.22-4.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/></svg>
        </a>
    @endif
</div>
