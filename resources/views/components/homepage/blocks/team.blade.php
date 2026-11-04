@php
    $areas = collect($data['areas'] ?? [])
        ->map(fn ($area) => \App\Enums\TeamArea::tryFrom((string) $area))
        ->filter()
        ->values();

    $membersByArea = \App\Models\User::query()
        ->teamMembers()
        ->when($areas->isNotEmpty(), fn ($query) => $query->whereIn('team_area', $areas->map->value->all()))
        ->with([
            'interests' => fn ($query) => $query->active(),
            'responsibleCourses' => fn ($query) => $query->forHomepage(),
        ])
        ->get()
        ->groupBy(fn (\App\Models\User $member): string => $member->team_area->value);

    $showHeadings = $data['show_area_headings'] ?? true;
    $privacySection = \App\Models\Section::query()->where('slug', 'datenschutz')->where('is_visible', true)->first();
@endphp

@if ($membersByArea->isNotEmpty())
    <div class="flex flex-col gap-10">
        @foreach (\App\Enums\TeamArea::cases() as $area)
            @continue(! $membersByArea->has($area->value))

            <section aria-label="{{ $area->getLabel() }}" class="flex flex-col gap-5">
                @if ($showHeadings)
                    <h3 class="text-xl font-bold text-brand-heading">{{ $area->getLabel() }}</h3>
                @endif

                <ul class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                    @foreach ($membersByArea[$area->value] as $member)
                        <li>
                            <x-homepage.team-card :member="$member" :privacy-section="$privacySection" />
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
@endif
