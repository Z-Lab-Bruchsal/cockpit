@php
    $isArranging = isset($section) && $section?->exists
        && request()->boolean('anordnen')
        && (bool) \Illuminate\Support\Facades\Auth::guard('web')->user()?->can('reorder', \App\Models\Section::class);
@endphp

@if ($isArranging && filled($data['block_id'] ?? null))
    <livewire:homepage.tile-editor :section-id="$section->id" :block-id="$data['block_id']" :key="'tile-editor-'.$data['block_id']" />
@else
    <x-homepage.tiles :tiles="\App\Services\Homepage\Tile::collect($data['tiles'] ?? [])" />
@endif
