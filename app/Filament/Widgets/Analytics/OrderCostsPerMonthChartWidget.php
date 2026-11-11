<?php

namespace App\Filament\Widgets\Analytics;

use AlexanderGabriel\FilamentDashboardTabs\Contracts\HasDashboardLabel;
use App\Filament\Widgets\Analytics\Concerns\InteractsWithAnalytics;
use App\Models\Order;
use App\Models\User;
use Filament\Widgets\ChartWidget;

class OrderCostsPerMonthChartWidget extends ChartWidget implements HasDashboardLabel
{
    use InteractsWithAnalytics;

    protected ?string $heading = 'Bestellkosten pro Monat';

    protected ?string $maxHeight = '280px';

    public static function getDashboardLabel(): string
    {
        return 'Auswertung: Bestellkosten pro Monat';
    }

    protected static function canViewData(User $user): bool
    {
        return $user->can('viewAny', Order::class);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $months = $this->analytics()->orderCostsPerMonth($this->analyticsFilters());

        return [
            'datasets' => [
                [
                    'label' => 'Kosten (€)',
                    'data' => array_values($months),
                    'backgroundColor' => self::PALETTE[2],
                    'borderColor' => self::PALETTE[2],
                ],
            ],
            'labels' => array_keys($months),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }
}
