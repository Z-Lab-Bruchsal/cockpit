@php
    $layout = $template->detail_layout ?? \App\Enums\CourseDetailLayout::HeroImage;
    $image = $layout === \App\Enums\CourseDetailLayout::Minimal ? null : $course->detailImage();
@endphp

<x-homepage.layout :title="$course->name">
    <div style="{{ $template->accentStyle() }}">
        @if ($image && $layout === \App\Enums\CourseDetailLayout::HeroImage)
            <div class="relative h-56 overflow-hidden bg-gray-100 sm:h-80 lg:h-96">
                <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" class="size-full object-cover">
            </div>
        @endif

        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
            <nav aria-label="Brotkrümel" class="mb-6 text-sm text-gray-500">
                <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <li><a href="{{ route('homepage.index') }}" class="hover:text-gray-900 hover:underline">Start</a></li>
                    <li aria-hidden="true">/</li>
                    <li><a href="{{ route('homepage.courses.index') }}" class="hover:text-gray-900 hover:underline">Kurse</a></li>
                    <li aria-hidden="true">/</li>
                    <li aria-current="page" class="font-medium text-gray-900">{{ $course->name }}</li>
                </ol>
            </nav>

            <div @class([
                'grid gap-8',
                'lg:grid-cols-2 lg:items-start' => $image && $layout === \App\Enums\CourseDetailLayout::SideImage,
            ])>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">{{ $course->name }}</h1>

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @if ($template->show_age_range && $course->ageRangeLabel())
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ $course->ageRangeLabel() }}</span>
                        @endif
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ $course->audience->getLabel() }}</span>
                        @if ($template->show_interests)
                            @foreach ($course->interests as $interest)
                                <x-homepage.interest-badge :interest="$interest" />
                            @endforeach
                        @endif
                    </div>

                    @if ($course->teaser)
                        <p class="mt-4 text-lg text-gray-700">{{ $course->teaser }}</p>
                    @endif

                    <x-homepage.rich-text :html="$course->description" class="mt-6" />
                </div>

                @if ($image && $layout === \App\Enums\CourseDetailLayout::SideImage)
                    <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" class="w-full rounded-2xl object-cover">
                @endif
            </div>

            <section class="mt-12" aria-labelledby="termine">
                <h2 id="termine" class="mb-4 text-2xl font-bold tracking-tight text-gray-900">Termine & Anmeldung</h2>

                @if ($course->upcomingInstances->isEmpty())
                    <p class="rounded-2xl bg-gray-50 px-6 py-8 text-center text-gray-500">Für diesen Kurs sind gerade keine Termine geplant. Schau bald wieder vorbei!</p>
                @else
                    <div class="grid gap-4 lg:grid-cols-2">
                        @foreach ($course->upcomingInstances as $instance)
                            <x-homepage.course-instance :instance="$instance" :template="$template" />
                        @endforeach
                    </div>
                @endif
            </section>

            @if (! empty($template->extra_content))
                <div class="mt-12 flex flex-col gap-8">
                    @foreach ($template->extra_content as $block)
                        <x-homepage.block :block="$block" />
                    @endforeach
                </div>
            @endif

            <div class="mt-10">
                <a href="{{ route('homepage.courses.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand hover:underline">
                    <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.61l4.22 4.22a.75.75 0 1 1-1.06 1.06l-5.5-5.5a.75.75 0 0 1 0-1.06l5.5-5.5a.75.75 0 1 1 1.06 1.06L5.61 9.25h10.64A.75.75 0 0 1 17 10Z" clip-rule="evenodd"/></svg>
                    Alle Kurse
                </a>
            </div>
        </div>
    </div>
</x-homepage.layout>
