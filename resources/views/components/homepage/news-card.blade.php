@props(['post', 'compact' => false])

<a href="{{ $post->url() }}" @class([
    'key-card key-card-interactive group flex flex-col overflow-hidden focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-ink',
    'sm:flex-row' => ! $compact,
])>
    @if ($post->image_path)
        <div @class([
            'aspect-[4/3] shrink-0 overflow-hidden bg-gray-100',
            'sm:aspect-auto sm:w-1/3' => ! $compact,
        ])>
            <img src="{{ \App\Services\Homepage\HomepageImage::url($post->image_path) }}" alt="{{ $post->image_alt }}" loading="lazy" class="size-full object-cover transition duration-300 group-hover:scale-105">
        </div>
    @endif
    <div @class(['flex grow flex-col gap-3', 'p-6 sm:p-7' => ! $compact, 'p-5 sm:p-6' => $compact])>
        <time datetime="{{ $post->published_at->toDateString() }}" class="text-sm text-gray-500">{{ $post->published_at->format('d.m.Y') }}</time>
        @if ($compact)
            <h3 class="text-lg font-bold text-brand-heading sm:text-xl">{{ $post->title }}</h3>
        @else
            <h2 class="text-xl font-bold text-brand-heading sm:text-2xl">{{ $post->title }}</h2>
        @endif
        @if ($post->teaser)
            <p @class(['leading-relaxed', 'line-clamp-4' => $compact])>{{ $post->teaser }}</p>
        @endif
        <span class="mt-auto inline-flex items-center gap-1 text-sm font-semibold text-brand-ink group-hover:underline">
            Weiterlesen
            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.64l-4.22-4.22a.75.75 0 1 1 1.06-1.06l5.5 5.5a.75.75 0 0 1 0 1.06l-5.5 5.5a.75.75 0 1 1-1.06-1.06l4.22-4.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/></svg>
        </span>
    </div>
</a>
