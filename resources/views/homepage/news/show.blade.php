<x-homepage.layout :title="$post->title">
    <article class="mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-12">
        <nav aria-label="Brotkrümel" class="mb-6 text-sm text-gray-500">
            <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <li><a href="{{ route('homepage.index') }}" class="hover:text-gray-900 hover:underline">Start</a></li>
                <li aria-hidden="true">/</li>
                <li><a href="{{ route('homepage.news.index') }}" class="hover:text-gray-900 hover:underline">News</a></li>
                <li aria-hidden="true">/</li>
                <li aria-current="page" class="font-medium text-gray-900">{{ $post->title }}</li>
            </ol>
        </nav>

        <header class="mb-6">
            <time datetime="{{ $post->published_at->toDateString() }}" class="text-sm text-gray-500">{{ $post->published_at->format('d.m.Y') }}</time>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-brand-heading sm:text-4xl">{{ $post->title }}</h1>
            @if ($post->teaser)
                <p class="mt-3 text-lg leading-relaxed text-gray-700">{{ $post->teaser }}</p>
            @endif
        </header>

        @if ($post->image_path)
            <img src="{{ \App\Services\Homepage\HomepageImage::url($post->image_path) }}" alt="{{ $post->image_alt }}" class="mb-8 w-full rounded-2xl object-cover">
        @endif

        @if (! empty($post->content))
            <div class="flex flex-col gap-8">
                @foreach ($post->content as $block)
                    <x-homepage.block :block="$block" />
                @endforeach
            </div>
        @endif

        <div class="mt-10">
            <a href="{{ route('homepage.news.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-ink hover:underline">
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.61l4.22 4.22a.75.75 0 1 1-1.06 1.06l-5.5-5.5a.75.75 0 0 1 0-1.06l5.5-5.5a.75.75 0 1 1 1.06 1.06L5.61 9.25h10.64A.75.75 0 0 1 17 10Z" clip-rule="evenodd"/></svg>
                Zurück zu den News
            </a>
        </div>
    </article>
</x-homepage.layout>
