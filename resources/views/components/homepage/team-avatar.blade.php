@props(['member'])

@if ($member->teamPhotoUrl())
    <img src="{{ $member->teamPhotoUrl() }}" alt="{{ $member->team_photo_alt ?: $member->displayName() }}" loading="lazy" {{ $attributes->class('rounded-full object-cover') }}>
@else
    <span aria-hidden="true" {{ $attributes->class('flex items-center justify-center rounded-full bg-brand font-bold text-brand-contrast') }}>{{ $member->initials() }}</span>
@endif
