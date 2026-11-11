<?php

namespace App\Filament\Widgets\Analytics;

use AlexanderGabriel\FilamentDashboardTabs\Contracts\HasDashboardLabel;
use App\Filament\Widgets\Analytics\Concerns\InteractsWithAnalytics;
use Filament\Widgets\ChartWidget;

class EnrollmentsPerMonthChartWidget extends ChartWidget implements HasDashboardLabel
{
    use InteractsWithAnalytics;

    protected ?string $heading = 'Anmeldungen pro Monat';

    protected ?string $description = 'Nach Monat des Kursbeginns';

    protected ?string $maxHeight = '280px';

    public static function getDashboardLabel(): string
    {
        return 'Auswertung: Anmeldungen pro Monat';
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $months = $this->analytics()->enrollmentsPerMonth($this->analyticsFilters());

        return [
            'datasets' => [
                [
                    'label' => 'Anmeldungen',
                    'data' => array_values($months),
                    'borderColor' => self::PALETTE[1],
                    'backgroundColor' => self::PALETTE[1].'33',
                    'fill' => true,
                    'tension' => 0.3,
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
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
