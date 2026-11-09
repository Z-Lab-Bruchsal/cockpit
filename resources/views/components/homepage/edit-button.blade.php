@props(['url', 'label' => 'Bearbeiten'])

{{-- Pencil shown in edit mode (?bearbeiten=1), opens the inline editor dialog of the layout. --}}
@if ($url)
    <button
        type="button"
        data-edit-url="{{ $url }}"
        x-data
        x-on:click.prevent.stop="$dispatch('open-inline-editor', { url: @js($url) })"
        {{ $attributes->class('z-20 inline-flex items-center gap-1 rounded-lg bg-ink/90 px-2.5 py-1.5 text-xs font-semibold text-white shadow-lg hover:bg-ink') }}
    >
        <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="m5.433 13.917 1.262-3.155A4 4 0 0 1 7.58 9.42l6.92-6.918a2.121 2.121 0 0 1 3 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 0 1-.65-.65Z"/><path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0 0 10 3H4.75A2.75 2.75 0 0 0 2 5.75v9.5A2.75 2.75 0 0 0 4.75 18h9.5A2.75 2.75 0 0 0 17 15.25V10a.75.75 0 0 0-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5Z"/></svg>
        {{ $label }}
    </button>
@endif
