@props(['tiles', 'parentId' => null])

@php
    $isArranging = request()->boolean('anordnen')
        && (bool) \Illuminate\Support\Facades\Auth::guard('web')->user()?->can('reorder', \App\Models\Section::class);
@endphp

@if ($isArranging)
    <div {{ $attributes }}>
        <livewire:homepage.tile-editor :parent-id="$parentId" />
    </div>
@elseif ($tiles->isNotEmpty())
    <div {{ $attributes->class('grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-flow-dense lg:auto-rows-[minmax(13rem,auto)] lg:grid-cols-6') }}>
        @foreach ($tiles as $tile)
            <x-homepage.tile :section="$tile" />
        @endforeach
    </div>
@endif
