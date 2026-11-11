<?php

namespace App\Filament\Pages;

use AlexanderGabriel\FilamentDashboardTabs\Pages\Dashboard as TabsDashboard;
use App\Filament\Widgets\Analytics\AnalyticsFilterForm;
use App\Services\Reports\AnalyticsFilters;
use Filament\Schemas\Schema;
use Override;

/**
 * Dashboard with tabs; each tab keeps its own analytics filters.
 */
class Dashboard extends TabsDashboard
{
    #[Override]
    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns(AnalyticsFilterForm::columns())
            ->components(AnalyticsFilterForm::components());
    }

    #[Override]
    public function getFiltersDescription(): ?string
    {
        return $this->showsFiltersForm() ? AnalyticsFilters::fromArray($this->filters)->describe() : null;
    }
}
