@props(['block'])

@php
    $type = $block['type'] ?? null;
    $view = 'components.homepage.blocks.'.str_replace('_', '-', (string) $type);
    $anchor = \Illuminate\Support\Str::slug((string) ($block['data']['anchor'] ?? ''));
@endphp

@if ($type && view()->exists($view))
    @if ($anchor !== '')
        <div id="{{ $anchor }}" class="scroll-mt-24">
            @include($view, ['data' => $block['data'] ?? []])
        </div>
    @else
        @include($view, ['data' => $block['data'] ?? []])
    @endif
@endif
