@props(['block'])

@php
    $type = $block['type'] ?? null;
    $view = 'components.homepage.blocks.'.str_replace('_', '-', (string) $type);
@endphp

@if ($type && view()->exists($view))
    @include($view, ['data' => $block['data'] ?? []])
@endif
