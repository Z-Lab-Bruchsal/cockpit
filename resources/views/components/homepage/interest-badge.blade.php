@props(['interest'])

@php($color = Str::sanitizeCssColor($interest->color) ?? '#6b7280')

<span {{ $attributes->class('rounded-full px-2 py-0.5 text-xs font-medium') }} style="background-color: {{ $color }}; color: {{ \App\Models\CompanySetting::contrastTextColor($color) }}">{{ $interest->name }}</span>
