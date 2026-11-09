@php
    $instances = \App\Models\CourseInstance::query()
        ->forOffers(
            participations: array_values(array_filter(array_map(fn ($value) => \App\Enums\CourseParticipation::tryFrom((string) $value), (array) ($data['participations'] ?? [])))),
            interestIds: array_map('intval', (array) ($data['interest_ids'] ?? [])),
            audience: \App\Enums\CourseAudience::tryFrom((string) ($data['audience'] ?? '')),
        )
        ->limit(max(1, min(48, (int) ($data['limit'] ?? 24))))
        ->get();
@endphp

<x-homepage.offer-grid :instances="$instances" :tabs="(bool) ($data['show_filter_tabs'] ?? true)" />
