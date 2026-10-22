@props(['courses'])

@if ($courses->isEmpty())
    <p {{ $attributes->class('rounded-2xl bg-white px-6 py-10 text-center text-gray-500') }}>Aktuell sind keine passenden Kurse geplant.</p>
@else
    <div {{ $attributes->class('grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3') }}>
        @foreach ($courses as $course)
            <x-homepage.course-card :course="$course" />
        @endforeach
    </div>
@endif
