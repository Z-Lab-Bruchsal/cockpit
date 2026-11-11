<?php

namespace AlexanderGabriel\FilamentDashboardTabs\Concerns;

use AlexanderGabriel\FilamentDashboardTabs\Models\DashboardTabWidget;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Support\Icons\Heroicon;

/**
 * Filter form above the widget grid whose state is stored per tab. Widgets that use
 * Filament's InteractsWithPageFilters receive the state of their tab as $pageFilters.
 *
 * Override filtersForm() in a subclass of the dashboard to add filter components;
 * without components no filter form is shown.
 */
trait HasTabFilters
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $filters = null;

    #[Unexposed]
    public function bootedHasTabFilters(): void
    {
        $this->cacheSchema('filtersForm', $this->getFiltersForm());
    }

    #[Unexposed]
    public function filtersForm(Schema $schema): Schema
    {
        return $schema;
    }

    #[Unexposed]
    public function getFiltersForm(): Schema
    {
        if ((! $this->isCachingSchemas) && $this->hasCachedSchema('filtersForm')) {
            return $this->getSchema('filtersForm');
        }

        $schema = $this->makeSchema()
            ->columns([
                'md' => 2,
                'xl' => 3,
                '2xl' => 4,
            ])
            ->live()
            ->statePath('filters');

        return $this->filtersForm($schema);
    }

    #[Unexposed]
    public function hasFiltersForm(): bool
    {
        return $this->getFiltersForm()->getComponents() !== [];
    }

    /**
     * Whether the filter form is shown on the current tab: only when one of its widgets reads the filters.
     */
    #[Unexposed]
    public function showsFiltersForm(): bool
    {
        return $this->hasFiltersForm()
            && $this->currentWidgets->contains(fn (DashboardTabWidget $widget): bool => static::widgetUsesFilters($widget->widget));
    }

    /**
     * @param  class-string  $widget
     */
    public static function widgetUsesFilters(string $widget): bool
    {
        return property_exists($widget, 'pageFilters');
    }

    /**
     * Livewire properties for a widget of the current tab.
     *
     * @param  class-string  $widget
     * @return array<string, mixed>
     */
    #[Unexposed]
    public function widgetProperties(string $widget): array
    {
        return [
            ...$widget::getDefaultProperties(),
            ...(static::widgetUsesFilters($widget) ? ['pageFilters' => $this->filters] : []),
        ];
    }

    /**
     * Short text describing the active filters, e.g. for printouts. Null hides it.
     */
    #[Unexposed]
    public function getFiltersDescription(): ?string
    {
        return null;
    }

    #[Unexposed]
    public function updatedFilters(): void
    {
        if ($this->currentTab === null || ! $this->hasFiltersForm()) {
            return;
        }

        $this->ownedTab($this->currentTab->id)->update(['filters' => $this->filters]);
    }

    public function resetFiltersAction(): Action
    {
        return Action::make('resetFilters')
            ->label(__('filament-dashboard-tabs::dashboard-tabs.actions.reset_filters'))
            ->icon(Heroicon::ArrowPath)
            ->color('gray')
            ->link()
            ->action(function (): void {
                $this->getFiltersForm()->fill();
                $this->updatedFilters();
            });
    }

    /**
     * Fills the filter form with the stored state of the current tab, or its defaults.
     */
    protected function loadTabFilters(): void
    {
        if (! $this->hasFiltersForm()) {
            return;
        }

        $stored = $this->currentTab?->filters;

        $this->filters = null;
        $this->getFiltersForm()->fill(filled($stored) ? $stored : null);
    }
}
