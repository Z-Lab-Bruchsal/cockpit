@props(['block', 'section' => null])

@php
    $type = $block['type'] ?? null;
    $view = 'components.homepage.blocks.'.str_replace('_', '-', (string) $type);
    $anchor = \Illuminate\Support\Str::slug((string) ($block['data']['anchor'] ?? ''));
    // Tiles and buttons use the anchor field as the jump mark of their link target, not as their own id.
    $anchor = in_array($type, ['tiles', 'button'], true) ? '' : $anchor;
@endphp

@if ($type && view()->exists($view))
    @if ($editUrl = \App\Services\Homepage\InlineEditing::blockUrl($section, $block))
        <div class="relative rounded-2xl outline-2 outline-offset-4 outline-dashed outline-brand-ink/30" data-block-id="{{ $block['data']['block_id'] }}">
            <x-homepage.edit-button :url="$editUrl" label="Block bearbeiten" class="absolute -top-3 right-2" />
            <div @if ($anchor !== '') id="{{ $anchor }}" class="scroll-mt-24" @endif>
                @include($view, ['data' => $block['data'] ?? [], 'section' => $section])
            </div>
        </div>
    @elseif ($anchor !== '')
        <div id="{{ $anchor }}" class="scroll-mt-24">
            @include($view, ['data' => $block['data'] ?? [], 'section' => $section])
        </div>
    @else
        @include($view, ['data' => $block['data'] ?? [], 'section' => $section])
    @endif
@endif
