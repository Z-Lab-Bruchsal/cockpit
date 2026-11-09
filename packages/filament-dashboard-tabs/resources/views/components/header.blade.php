<header class="fi-dashboard-tabs-bar flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl bg-white px-2 py-1 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <h1 class="fi-header-heading shrink-0 text-lg sm:text-xl">
        {{ $this->getHeading() }}
    </h1>

    @if ($this->isEditing)
        <x-filament::tabs wire:key="dashboard-tabs-editing" wire:sort="sortTabs" class="min-w-0 flex-1">
            @foreach ($this->tabs as $tab)
                <x-filament::tabs.item
                    wire:key="dashboard-tab-editing-{{ $tab->id }}"
                    wire:sort:item="{{ $tab->id }}"
                    :active="$tab->id === $this->currentTab?->id"
                    wire:click="$set('activeTab', {{ $tab->id }})"
                    :icon="\Filament\Support\Icons\Heroicon::Bars2"
                    class="cursor-grab active:cursor-grabbing"
                >
                    {{ $tab->name }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    @elseif ($this->tabs->count() > 1)
        <x-filament::tabs wire:key="dashboard-tabs" class="min-w-0 flex-1">
            @foreach ($this->tabs as $tab)
                <x-filament::tabs.item
                    wire:key="dashboard-tab-{{ $tab->id }}"
                    :active="$tab->id === $this->currentTab?->id"
                    wire:click="$set('activeTab', {{ $tab->id }})"
                >
                    {{ $tab->name }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    @else
        <div class="flex-1"></div>
    @endif

    <div class="ms-auto flex shrink-0 flex-wrap items-center gap-3">
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE, scopes: $this->getRenderHookScopes()) }}

        <x-filament::actions :actions="$this->getCachedHeaderActions()" />

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::PAGE_HEADER_ACTIONS_AFTER, scopes: $this->getRenderHookScopes()) }}
    </div>
</header>
