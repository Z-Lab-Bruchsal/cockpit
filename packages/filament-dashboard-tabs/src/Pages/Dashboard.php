<?php

namespace AlexanderGabriel\FilamentDashboardTabs\Pages;

use AlexanderGabriel\FilamentDashboardTabs\Enums\WidgetWidth;
use AlexanderGabriel\FilamentDashboardTabs\FilamentDashboardTabsPlugin;
use AlexanderGabriel\FilamentDashboardTabs\Models\DashboardTab;
use AlexanderGabriel\FilamentDashboardTabs\Models\DashboardTabWidget;
use AlexanderGabriel\FilamentDashboardTabs\Support\WidgetRegistry;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * Dashboard where every user arranges panel widgets on their own tabs:
 * drag to reorder, toggle between half and full width, move between tabs.
 */
class Dashboard extends BaseDashboard
{
    protected string $view = 'filament-dashboard-tabs::pages.dashboard';

    #[Url(as: 'tab')]
    public ?int $activeTab = null;

    public bool $isEditing = false;

    public function mount(): void
    {
        $this->createDefaultTabIfMissing();
    }

    /**
     * @return Collection<int, DashboardTab>
     */
    #[Computed]
    public function tabs(): Collection
    {
        return $this->tabsQuery()->get();
    }

    #[Computed]
    public function currentTab(): ?DashboardTab
    {
        return $this->tabs->firstWhere('id', $this->activeTab) ?? $this->tabs->first();
    }

    /**
     * Widgets of the current tab the user may see, in order.
     *
     * @return Collection<int, DashboardTabWidget>
     */
    #[Computed]
    public function currentWidgets(): Collection
    {
        if ($this->currentTab === null) {
            return collect();
        }

        return $this->visibleWidgetsOf($this->currentTab);
    }

    public function widgetLabel(string $widget): string
    {
        return $this->registry()->label($widget);
    }

    /**
     * Handler for wire:sort on the widget grid.
     */
    public function sortWidgets(int|string $id, int $position): void
    {
        $widget = $this->ownedWidget($id);
        $tab = $widget->tab;

        $visible = $this->visibleWidgetsOf($tab);
        $hidden = $tab->widgets()->whereNotIn('id', $visible->modelKeys())->get();

        $this->reorder($visible, $widget->id, $position, $hidden);

        $this->refreshState();
    }

    /**
     * Handler for wire:sort on the tab bar.
     */
    public function sortTabs(int|string $id, int $position): void
    {
        $tab = $this->ownedTab($id);

        $this->reorder($this->tabsQuery()->get(), $tab->id, $position);

        $this->refreshState();
    }

    public function setWidth(int|string $id, string $width): void
    {
        $widget = $this->ownedWidget($id);
        $newWidth = WidgetWidth::tryFrom($width);

        if ($newWidth === null) {
            throw ValidationException::withMessages([
                'width' => __('filament-dashboard-tabs::dashboard-tabs.validation.width'),
            ]);
        }

        $widget->update(['width' => $newWidth]);

        $this->refreshState();
    }

    public function removeWidget(int|string $id): void
    {
        $this->ownedWidget($id)->delete();

        $this->refreshState();
    }

    /**
     * Single bar holding the heading, the tab switcher and the header actions.
     */
    #[Unexposed]
    public function getHeader(): ?View
    {
        return view('filament-dashboard-tabs::components.header');
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->addWidgetAction(),
            $this->createTabAction(),
            $this->renameTabAction(),
            $this->deleteTabAction(),
            $this->toggleEditingAction(),
        ];
    }

    public function toggleEditingAction(): Action
    {
        return Action::make('toggleEditing')
            ->label(fn (): string => __('filament-dashboard-tabs::dashboard-tabs.actions.'.($this->isEditing ? 'finish_editing' : 'edit')))
            ->icon(fn (): Heroicon => $this->isEditing ? Heroicon::Check : Heroicon::PencilSquare)
            ->color(fn (): string => $this->isEditing ? 'primary' : 'gray')
            ->action(function (): void {
                $this->isEditing = ! $this->isEditing;
            });
    }

    public function createTabAction(): Action
    {
        return Action::make('createTab')
            ->label(__('filament-dashboard-tabs::dashboard-tabs.actions.create_tab'))
            ->icon(Heroicon::Plus)
            ->color('gray')
            ->visible(fn (): bool => $this->isEditing)
            ->disabled(fn (): bool => $this->hasReachedMaxTabs())
            ->schema([
                $this->tabNameInput(),
            ])
            ->action(function (array $data): void {
                abort_if($this->hasReachedMaxTabs(), 403);

                $tab = $this->tabsQuery()->create([
                    'name' => $data['name'],
                    'sort' => (int) $this->tabsQuery()->max('sort') + 1,
                ]);

                $this->activeTab = $tab->id;
                $this->refreshState();
            });
    }

    public function renameTabAction(): Action
    {
        return Action::make('renameTab')
            ->label(__('filament-dashboard-tabs::dashboard-tabs.actions.rename_tab'))
            ->icon(Heroicon::Pencil)
            ->color('gray')
            ->visible(fn (): bool => $this->isEditing && $this->currentTab !== null)
            ->fillForm(fn (): array => ['name' => $this->currentTab?->name])
            ->schema([
                $this->tabNameInput(),
            ])
            ->action(function (array $data): void {
                $this->ownedTab($this->currentTab->id)->update(['name' => $data['name']]);

                $this->refreshState();
            });
    }

    public function deleteTabAction(): Action
    {
        return Action::make('deleteTab')
            ->label(__('filament-dashboard-tabs::dashboard-tabs.actions.delete_tab'))
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (): string => __('filament-dashboard-tabs::dashboard-tabs.actions.delete_tab_heading', ['name' => $this->currentTab?->name]))
            ->visible(fn (): bool => $this->isEditing && $this->tabs->count() > 1)
            ->action(function (): void {
                abort_if($this->tabs->count() <= 1, 403);

                $this->ownedTab($this->currentTab->id)->delete();

                $this->activeTab = null;
                $this->refreshState();
            });
    }

    public function addWidgetAction(): Action
    {
        return Action::make('addWidget')
            ->label(__('filament-dashboard-tabs::dashboard-tabs.actions.add_widget'))
            ->icon(Heroicon::SquaresPlus)
            ->visible(fn (): bool => $this->isEditing && $this->currentTab !== null)
            ->disabled(fn (): bool => $this->addableWidgetOptions() === [])
            ->schema([
                Select::make('widget')
                    ->label(__('filament-dashboard-tabs::dashboard-tabs.fields.widget'))
                    ->options(fn (): array => $this->addableWidgetOptions())
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        if (filled($state) && $this->registry()->isAvailable($state)) {
                            $set('width', $this->registry()->defaultWidth($state));
                        }
                    }),
                Radio::make('width')
                    ->label(__('filament-dashboard-tabs::dashboard-tabs.fields.width'))
                    ->options(WidgetWidth::class)
                    ->default(WidgetWidth::Full)
                    ->inline()
                    ->required(),
            ])
            ->action(function (array $data): void {
                $tab = $this->ownedTab($this->currentTab->id);

                abort_unless(array_key_exists($data['widget'], $this->addableWidgetOptions()), 403);

                $tab->widgets()->create([
                    'widget' => $data['widget'],
                    'width' => $data['width'],
                    'sort' => (int) $tab->widgets()->max('sort') + 1,
                ]);

                $this->refreshState();
            });
    }

    public function moveWidgetAction(): Action
    {
        return Action::make('moveWidget')
            ->label(__('filament-dashboard-tabs::dashboard-tabs.actions.move_widget'))
            ->icon(Heroicon::ArrowsRightLeft)
            ->iconButton()
            ->color('gray')
            ->size('sm')
            ->visible(fn (array $arguments): bool => $this->moveTargetOptions($arguments['widget'] ?? 0) !== [])
            ->schema(fn (array $arguments): array => [
                Select::make('target')
                    ->label(__('filament-dashboard-tabs::dashboard-tabs.fields.target_tab'))
                    ->options($this->moveTargetOptions($arguments['widget'] ?? 0))
                    ->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                $widget = $this->ownedWidget($arguments['widget'] ?? 0);

                abort_unless(array_key_exists((int) $data['target'], $this->moveTargetOptions($widget->id)), 403);

                $target = $this->ownedTab($data['target']);

                $widget->update([
                    'dashboard_tab_id' => $target->id,
                    'sort' => (int) $target->widgets()->max('sort') + 1,
                ]);

                $this->refreshState();
            });
    }

    /**
     * @return array<class-string, string>
     */
    protected function addableWidgetOptions(): array
    {
        if ($this->currentTab === null) {
            return [];
        }

        $existing = $this->currentTab->widgets()->pluck('widget')->all();

        return array_diff_key($this->registry()->options(), array_flip($existing));
    }

    /**
     * Other tabs of the user that do not yet contain this widget.
     *
     * @return array<int, string>
     */
    protected function moveTargetOptions(int|string $widgetId): array
    {
        $widget = DashboardTabWidget::query()
            ->whereKey($widgetId)
            ->whereHas('tab', fn ($query) => $query->where('user_id', $this->user()->getKey()))
            ->first();

        if ($widget === null) {
            return [];
        }

        return $this->tabsQuery()
            ->whereKeyNot($widget->dashboard_tab_id)
            ->whereDoesntHave('widgets', fn ($query) => $query->where('widget', $widget->widget))
            ->pluck('name', 'id')
            ->all();
    }

    protected function tabNameInput(): TextInput
    {
        return TextInput::make('name')
            ->label(__('filament-dashboard-tabs::dashboard-tabs.fields.name'))
            ->required()
            ->maxLength(100);
    }

    protected function hasReachedMaxTabs(): bool
    {
        $maxTabs = FilamentDashboardTabsPlugin::get()->getMaxTabs();

        return $maxTabs !== null && $this->tabsQuery()->count() >= $maxTabs;
    }

    /**
     * @return Collection<int, DashboardTabWidget>
     */
    protected function visibleWidgetsOf(DashboardTab $tab): Collection
    {
        $available = $this->registry()->available();

        return $tab->widgets()->get()
            ->filter(fn (DashboardTabWidget $widget): bool => in_array($widget->widget, $available, true))
            ->values();
    }

    /**
     * Moves one model to a new position and renumbers the list. Models in $trailing keep their
     * relative order after the sorted ones (e.g. widgets the user currently cannot see).
     *
     * @param  Collection<int, Model>  $models
     * @param  Collection<int, Model>|null  $trailing
     */
    protected function reorder(Collection $models, int $movedId, int $position, ?Collection $trailing = null): void
    {
        $ids = $models->modelKeys();
        $ids = array_values(array_diff($ids, [$movedId]));
        $position = max(0, min($position, count($ids)));

        array_splice($ids, $position, 0, [$movedId]);

        $ordered = [...$ids, ...($trailing?->modelKeys() ?? [])];
        $byId = $models->concat($trailing ?? [])->keyBy(fn (Model $model): int => $model->getKey());

        DB::transaction(function () use ($ordered, $byId): void {
            foreach ($ordered as $sort => $id) {
                $model = $byId[$id];

                if ($model->getAttribute('sort') !== $sort) {
                    $model->update(['sort' => $sort]);
                }
            }
        });
    }

    protected function createDefaultTabIfMissing(): void
    {
        if ($this->tabsQuery()->exists()) {
            return;
        }

        DB::transaction(function (): void {
            $tab = $this->tabsQuery()->create([
                'name' => __('filament-dashboard-tabs::dashboard-tabs.default_tab'),
                'sort' => 0,
            ]);

            foreach ($this->registry()->defaults() as $sort => $widget) {
                $tab->widgets()->create([
                    'widget' => $widget,
                    'width' => $this->registry()->defaultWidth($widget),
                    'sort' => $sort,
                ]);
            }
        });
    }

    protected function ownedTab(int|string $id): DashboardTab
    {
        return $this->tabsQuery()->findOrFail($id);
    }

    protected function ownedWidget(int|string $id): DashboardTabWidget
    {
        return DashboardTabWidget::query()
            ->whereHas('tab', fn ($query) => $query->where('user_id', $this->user()->getKey()))
            ->findOrFail($id);
    }

    /**
     * @return HasMany<DashboardTab, Model>
     */
    protected function tabsQuery(): HasMany
    {
        return $this->user()->dashboardTabs();
    }

    protected function user(): Model
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof Model && method_exists($user, 'dashboardTabs'), 403);

        return $user;
    }

    protected function registry(): WidgetRegistry
    {
        return WidgetRegistry::make();
    }

    protected function refreshState(): void
    {
        unset($this->tabs, $this->currentTab, $this->currentWidgets);
    }
}
