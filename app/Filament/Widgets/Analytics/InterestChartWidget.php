<?php

namespace App\Filament\Widgets\Analytics;

use AlexanderGabriel\FilamentDashboardTabs\Contracts\HasDashboardLabel;
use App\Filament\Widgets\Analytics\Concerns\InteractsWithAnalytics;
use Filament\Widgets\ChartWidget;

class InterestChartWidget extends ChartWidget implements HasDashboardLabel
{
    use InteractsWithAnalytics;

    protected ?string $heading = 'Anmeldungen nach Interesse';

    protected ?string $description = 'Kurse mit mehreren Interessen zählen bei jedem';

    protected ?string $maxHeight = '280px';

    public static function getDashboardLabel(): string
    {
        return 'Auswertung: Interessen';
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
        $interests = $this->analytics()->enrollmentsPerInterest($this->analyticsFilters());

        return [
            'datasets' => [
                [
                    'label' => 'Anmeldungen',
                    'data' => array_values($interests),
                    'backgroundColor' => static::paletteFor(count($interests)),
                ],
            ],
            'labels' => array_keys($interests),
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
