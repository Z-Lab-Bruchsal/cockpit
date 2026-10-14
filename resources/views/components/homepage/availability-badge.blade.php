@props(['availability'])

<span {{ $attributes->class('inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold text-white') }} style="background-color: {{ $availability->hexColor() }}">
    {{ $availability->getLabel() }}
</span>
