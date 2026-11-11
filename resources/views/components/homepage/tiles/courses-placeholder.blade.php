{{-- A courses tile in the editor: one placeholder that stands for the course tiles on the live page. --}}
@php
    $itemWidth = \App\Models\Tile::WIDTHS[\App\Services\Homepage\TileCard::width($item->tile->setting('item_width', 2))] ?? '';
@endphp

<div class="flex flex-col gap-3 rounded-2xl border-2 border-dashed border-brand-ink/40 bg-white/80 p-5">
    <p class="flex items-center gap-2 font-bold text-ink">
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342"/></svg>
        {{ $item->tile->label() }}
    </p>
    <p class="text-sm text-gray-600">
        {{ $courses->count() === 1 ? '1 Kurs' : $courses->count().' Kurse' }}, je {{ $itemWidth }} –
        auf der Seite erscheint an dieser Stelle je Kurs eine eigene Kachel.
    </p>
    @if ($courses->isNotEmpty())
        <ul class="flex flex-wrap gap-1.5 text-xs">
            @foreach ($courses->take(12) as $course)
                <li class="rounded-full bg-gray-100 px-2 py-0.5 font-medium text-gray-700">{{ $course->name }}</li>
            @endforeach
            @if ($courses->count() > 12)
                <li class="px-1 py-0.5 text-gray-500">…</li>
            @endif
        </ul>
    @endif
</div>
