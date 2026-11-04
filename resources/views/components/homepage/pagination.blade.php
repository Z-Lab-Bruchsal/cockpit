{{-- Pagination view for the homepage, used via $paginator->links('components.homepage.pagination'). --}}
@if ($paginator->hasPages())
    <nav aria-label="Seitennavigation" class="flex items-center justify-between gap-4 text-sm">
        @if ($paginator->onFirstPage())
            <span class="rounded-lg px-4 py-2 text-gray-400">« Neuere</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="rounded-lg bg-white px-4 py-2 font-semibold text-brand-ink hover:underline">« Neuere</a>
        @endif

        <span class="text-gray-600">Seite {{ $paginator->currentPage() }} von {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="rounded-lg bg-white px-4 py-2 font-semibold text-brand-ink hover:underline">Ältere »</a>
        @else
            <span class="rounded-lg px-4 py-2 text-gray-400">Ältere »</span>
        @endif
    </nav>
@endif
