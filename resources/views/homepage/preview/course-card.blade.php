<x-homepage.layout :title="$course->name" is-preview>
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <p class="mb-4 text-sm text-gray-500">Visitenkarte in der Kursübersicht:</p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3">
            <x-homepage.course-card :course="$course" />
        </div>
    </div>
</x-homepage.layout>
