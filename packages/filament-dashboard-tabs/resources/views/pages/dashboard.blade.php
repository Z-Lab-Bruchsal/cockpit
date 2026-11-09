@php
    use AlexanderGabriel\FilamentDashboardTabs\Enums\WidgetWidth;
@endphp

<x-filament-panels::page>
    <div class="flex flex-col gap-3">
        @if ($isEditing)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('filament-dashboard-tabs::dashboard-tabs.editing_hint') }}
            </p>
        @endif

        @if ($this->currentWidgets->isEmpty())
            <x-filament::empty-state
                :heading="__('filament-dashboard-tabs::dashboard-tabs.empty.heading')"
                :description="__('filament-dashboard-tabs::dashboard-tabs.empty.description')"
                :icon="\Filament\Support\Icons\Heroicon::OutlinedSquares2x2"
            />
        @else
            {{-- Drag handles stay in the DOM (only hidden) so wire:sort keeps using handle mode and
                 widget components are not re-mounted when edit mode is toggled. --}}
            <div
                wire:key="dashboard-grid-{{ $this->currentTab->id }}"
                wire:sort="sortWidgets"
                class="grid grid-cols-1 gap-3 lg:grid-cols-2"
            >
                @foreach ($this->currentWidgets as $row)
                    @php
                        $label = $this->widgetLabel($row->widget);
                    @endphp

                    <div
                        wire:key="dashboard-widget-{{ $row->id }}"
                        wire:sort:item="{{ $row->id }}"
                        @class([
                            'flex min-w-0 flex-col gap-2',
                            'lg:col-span-2' => $row->width === WidgetWidth::Full,
                            'rounded-xl p-2 outline-2 outline-dashed outline-gray-300 dark:outline-white/20' => $isEditing,
                        ])
                    >
                        <div @class(['flex items-center gap-2', 'hidden' => ! $isEditing])>
                            <button
                                type="button"
                                wire:sort:handle
                                class="flex cursor-grab items-center gap-2 rounded-lg px-2 py-1 text-sm font-medium text-gray-700 hover:bg-gray-100 active:cursor-grabbing dark:text-gray-200 dark:hover:bg-white/5"
                                title="{{ __('filament-dashboard-tabs::dashboard-tabs.actions.drag') }}"
                            >
                                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::Bars3" class="size-5 text-gray-400" />
                                <span class="truncate">{{ $label }}</span>
                            </button>

                            <div class="ms-auto flex items-center gap-1">
                                <x-filament::button
                                    size="xs"
                                    color="gray"
                                    :icon="$row->width === WidgetWidth::Full ? \Filament\Support\Icons\Heroicon::ArrowsPointingIn : \Filament\Support\Icons\Heroicon::ArrowsPointingOut"
                                    wire:click="setWidth({{ $row->id }}, '{{ $row->width->toggled()->value }}')"
                                >
                                    {{ $row->width->toggled()->getLabel() }}
                                </x-filament::button>

                                {{ ($this->moveWidgetAction)(['widget' => $row->id]) }}

                                <x-filament::icon-button
                                    :icon="\Filament\Support\Icons\Heroicon::Trash"
                                    color="danger"
                                    size="sm"
                                    :label="__('filament-dashboard-tabs::dashboard-tabs.actions.remove_widget')"
                                    :tooltip="__('filament-dashboard-tabs::dashboard-tabs.actions.remove_widget')"
                                    wire:click="removeWidget({{ $row->id }})"
                                    wire:confirm="{{ __('filament-dashboard-tabs::dashboard-tabs.actions.remove_widget_confirm', ['name' => $label]) }}"
                                />
                            </div>
                        </div>

                        @livewire($row->widget, $row->widget::getDefaultProperties(), key('dashboard-tab-widget-'.$row->id))
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
