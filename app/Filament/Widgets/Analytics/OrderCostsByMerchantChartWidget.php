<?php

namespace App\Filament\Widgets\Analytics;

use AlexanderGabriel\FilamentDashboardTabs\Contracts\HasDashboardLabel;
use App\Filament\Widgets\Analytics\Concerns\InteractsWithAnalytics;
use App\Models\Order;
use App\Models\User;
use Filament\Widgets\ChartWidget;

class OrderCostsByMerchantChartWidget extends ChartWidget implements HasDashboardLabel
{
    use InteractsWithAnalytics;

    /**
     * Merchants shown individually; the rest is summed up as "Sonstige".
     */
    public const int TOP_MERCHANTS = 8;

    protected ?string $heading = 'Bestellkosten nach Händler';

    protected ?string $maxHeight = '280px';

    public static function getDashboardLabel(): string
    {
        return 'Auswertung: Bestellkosten nach Händler';
    }

    protected static function canViewData(User $user): bool
    {
        return $user->can('viewAny', Order::class);
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $merchants = $this->analytics()->orderCostsPerMerchant($this->analyticsFilters());
        $shown = array_slice($merchants, 0, self::TOP_MERCHANTS, true);
        $rest = round(array_sum(array_slice($merchants, self::TOP_MERCHANTS)), 2);

        if ($rest > 0) {
            $shown['Sonstige'] = $rest;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Kosten (€)',
                    'data' => array_values($shown),
                    'backgroundColor' => static::paletteFor(count($shown)),
                ],
            ],
            'labels' => array_keys($shown),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'right']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}
