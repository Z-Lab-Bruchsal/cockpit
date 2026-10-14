<x-homepage.layout title="Schulkonto">
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-12">
        @if (session('status'))
            <p class="mb-6 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</p>
        @endif

        @if (session('school_error'))
            <p class="mb-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ session('school_error') }}</p>
        @endif

        <livewire:schools.school-account />
    </div>
</x-homepage.layout>
