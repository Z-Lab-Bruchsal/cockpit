<x-homepage.layout title="Kurse">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
        <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-brand-heading sm:text-4xl">Kurse</h1>
                <p class="mt-2 text-gray-600">Alle kommenden Kurse im Überblick.</p>
            </div>
            <a href="{{ route('homepage.calendar') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-ink hover:underline">
                Zur Kalenderansicht
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.64l-4.22-4.22a.75.75 0 1 1 1.06-1.06l5.5 5.5a.75.75 0 0 1 0 1.06l-5.5 5.5a.75.75 0 1 1-1.06-1.06l4.22-4.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/></svg>
            </a>
        </div>

        <form method="GET" action="{{ route('homepage.courses.index') }}" class="mb-8 grid grid-cols-1 gap-3 rounded-2xl bg-white p-4 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end">
            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                Interesse
                <select name="interesse" class="rounded-lg border-gray-300 text-sm">
                    <option value="">Alle</option>
                    @foreach ($interests as $interest)
                        <option value="{{ $interest->slug }}" @selected(($filters['interesse'] ?? null) === $interest->slug)>{{ $interest->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                Alter des Kindes
                <input type="number" name="alter" min="0" max="99" value="{{ $filters['alter'] ?? '' }}" placeholder="z. B. 9" class="rounded-lg border-gray-300 text-sm">
            </label>
            <label class="flex flex-col gap-1 text-sm font-medium text-gray-700">
                Für
                <select name="zielgruppe" class="rounded-lg border-gray-300 text-sm">
                    <option value="">Alle</option>
                    @foreach (\App\Enums\CourseAudience::cases() as $audience)
                        <option value="{{ $audience->value }}" @selected(($filters['zielgruppe'] ?? null) === $audience->value)>{{ $audience->getLabel() }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-brand-contrast hover:opacity-90">Filtern</button>
                @if (array_filter($filters))
                    <a href="{{ route('homepage.courses.index') }}" class="rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100">Zurücksetzen</a>
                @endif
            </div>
        </form>

        <x-homepage.course-grid :courses="$courses" />
    </div>
</x-homepage.layout>
