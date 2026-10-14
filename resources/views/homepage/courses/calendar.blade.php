<x-homepage.layout title="Kalender">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
        <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Kalender</h1>
            <a href="{{ route('homepage.courses.index') }}" class="text-sm font-semibold text-brand hover:underline">Zur Kursübersicht</a>
        </div>

        <x-homepage.course-calendar />
    </div>
</x-homepage.layout>
