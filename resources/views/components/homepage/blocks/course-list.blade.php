@php
    $courses = \App\Models\Course::query()
        ->forHomepage(
            audience: \App\Enums\CourseAudience::tryFrom((string) ($data['audience'] ?? '')),
            interestIds: array_map('intval', $data['interest_ids'] ?? []),
        )
        ->limit(max(1, min(48, (int) ($data['limit'] ?? 6))))
        ->get();
@endphp

<div class="flex flex-col gap-4">
    <x-homepage.course-grid :courses="$courses" />

    @if ($data['show_all_link'] ?? true)
        <a href="{{ route('homepage.courses.index') }}" class="inline-flex items-center gap-1 self-start text-sm font-semibold text-brand-ink hover:underline">
            Alle Kurse ansehen
            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.64l-4.22-4.22a.75.75 0 1 1 1.06-1.06l5.5 5.5a.75.75 0 0 1 0 1.06l-5.5 5.5a.75.75 0 1 1-1.06-1.06l4.22-4.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/></svg>
        </a>
    @endif
</div>
