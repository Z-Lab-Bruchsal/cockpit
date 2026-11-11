@props(['block', 'section' => null])

{{-- A content block of a news post or course template. Section pages consist of tiles instead, see tile-grid. --}}
@php
    $type = $block['type'] ?? null;
    $view = 'components.homepage.blocks.'.str_replace('_', '-', (string) $type);
    $anchor = \Illuminate\Support\Str::slug((string) ($block['data']['anchor'] ?? ''));
    // Buttons use the anchor field as the jump mark of their link target, not as their own id.
    $anchor = $type === 'button' ? '' : $anchor;
@endphp

@if ($type && view()->exists($view))
    @if ($anchor !== '')
        <div id="{{ $anchor }}" class="scroll-mt-24">
            @include($view, ['data' => $block['data'] ?? [], 'section' => $section])
        </div>
    @else
        @include($view, ['data' => $block['data'] ?? [], 'section' => $section])
    @endif
@endif
