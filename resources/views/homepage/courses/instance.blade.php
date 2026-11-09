@php
    /** @var \App\Models\CourseInstance $instance */
    $course = $instance->course;
    $template = $course->effectiveTemplate();
    $image = $instance->detailImage();
    $label = $instance->cardLabel();
    $availability = $instance->availability();
    $settings = \App\Models\CompanySetting::current();
    $address = collect([$settings->street, trim($settings->zip.' '.$settings->city)])->filter()->implode(', ');
    $isPreview = $preview ?? false;
@endphp

<x-homepage.layout :title="$instance->displayTitle()" :is-preview="$isPreview">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12" style="{{ $template->accentStyle() }}">
        <nav aria-label="Brotkrümel" class="mb-6 text-sm text-gray-500">
            <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <li><a href="{{ route('homepage.index') }}" class="hover:text-gray-900 hover:underline">Start</a></li>
                <li aria-hidden="true">/</li>
                <li><a href="{{ route('homepage.courses.index') }}" class="hover:text-gray-900 hover:underline">Kurse</a></li>
                <li aria-hidden="true">/</li>
                <li aria-current="page" class="font-medium text-gray-900">{{ $instance->displayTitle() }}</li>
            </ol>
        </nav>

        @if (session('status'))
            <p class="mb-6 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</p>
        @endif

        <article class="key-card relative flex flex-col overflow-hidden md:flex-row" data-offer-id="{{ $instance->id }}">
            <x-homepage.edit-button :url="$isPreview ? null : \App\Services\Homepage\InlineEditing::offerUrl($instance)" label="Angebot bearbeiten" class="absolute top-3 right-3" />
            @if ($image)
                <div class="relative aspect-[4/3] shrink-0 overflow-hidden bg-gray-100 md:aspect-auto md:w-2/5">
                    <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" class="size-full object-cover">
                    <x-homepage.image-label :label="$label" class="top-6 left-6" />
                </div>
            @endif

            <div class="flex grow flex-col gap-5 p-6 sm:p-10">
                @unless ($image)
                    <x-homepage.image-label :label="$label" size="sm" :floating="false" />
                @endunless
                <h1 class="text-3xl font-bold tracking-tight text-brand-heading sm:text-4xl">{{ $instance->displayTitle() }}</h1>

                <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 font-semibold">
                    @if ($instance->isDropIn())
                        @if ($instance->schedule_text)
                            <dt>Wann</dt>
                            <dd>{{ $instance->schedule_text }}@if ($instance->schedule_note)<span class="block text-sm font-normal text-gray-600">{{ $instance->schedule_note }}</span>@endif</dd>
                        @endif
                        <dt>Anmeldung</dt>
                        <dd>Nicht nötig – einfach vorbeikommen</dd>
                    @elseif ($instance->firstDate)
                        <dt>Beginnt am</dt>
                        <dd>{{ $instance->firstDate->starts_at->locale('de')->isoFormat('DD. MM. YYYY') }}</dd>
                    @endif
                    @if ($course->audienceLabel())
                        <dt>Für</dt>
                        <dd>{{ $course->audienceLabel() }}</dd>
                    @endif
                    @if ($instance->location)
                        <dt>Wo</dt>
                        <dd>{{ $instance->location }}</dd>
                    @endif
                </dl>

                @if ($instance->teaserText())
                    <p class="text-lg text-gray-700">{{ $instance->teaserText() }}</p>
                @endif

                <x-homepage.rich-text :html="$instance->descriptionHtml()" />

                @if (! $instance->isDropIn() && $instance->dates->isNotEmpty())
                    <section aria-labelledby="sessions">
                        <h2 id="sessions" class="mb-2 text-xl font-bold">{{ $instance->dates->count() === 1 ? 'Termin' : 'Termine' }}</h2>
                        <ul class="flex flex-col gap-1 font-light">
                            @foreach ($instance->dates as $date)
                                <li>
                                    {{ $date->starts_at->locale('de')->isoFormat('D. MMMM YYYY') }}
                                    <span class="text-brand-ink" aria-hidden="true">|</span>
                                    {{ $date->starts_at->format('H:i') }} – {{ $date->ends_at->format('H:i') }} Uhr
                                    @if ($date->location && $date->location !== $instance->location)
                                        <span class="text-gray-500">({{ $date->location }})</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @unless ($instance->isDropIn())
                    <section id="anmeldung" class="scroll-mt-24" aria-label="Anmeldung">
                        <div class="mb-3"><x-homepage.availability-badge :availability="$availability" /></div>
                        @if ($isPreview)
                            <p class="text-sm italic text-gray-600">Die Anmeldung ist in der Vorschau deaktiviert.</p>
                        @elseif ($availability === \App\Enums\CourseAvailability::NotYetOpen)
                            <p class="text-sm text-gray-600">Anmeldung ab {{ $instance->registration_opens_at->locale('de')->isoFormat('dd, DD.MM.YYYY, HH:mm') }} Uhr möglich.</p>
                        @else
                            <x-homepage.course-registration-form :instance="$instance" :availability="$availability" />
                        @endif
                    </section>
                @endunless

                <div class="flex flex-wrap gap-3">
                    @if ($address !== '')
                        <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($address) }}" target="_blank" rel="noopener" class="key-button text-sm">Anfahrt →</a>
                    @endif
                    <a href="{{ $course->url() }}" class="key-button text-sm">Mehr zum Kurs</a>
                </div>
            </div>
        </article>
    </div>
</x-homepage.layout>
