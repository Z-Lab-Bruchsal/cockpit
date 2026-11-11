<?php

namespace App\Filament\Widgets\Analytics;

use AlexanderGabriel\FilamentDashboardTabs\Contracts\HasDashboardLabel;
use App\Filament\Widgets\Analytics\Concerns\InteractsWithAnalytics;
use Filament\Widgets\ChartWidget;

class KidAgeChartWidget extends ChartWidget implements HasDashboardLabel
{
    use InteractsWithAnalytics;

    protected ?string $heading = 'Alter der Kinder';

    protected ?string $description = 'Anmeldungen nach Alter zu Kursbeginn';

    protected ?string $maxHeight = '280px';

    public static function getDashboardLabel(): string
    {
        return 'Auswertung: Alter der Kinder';
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
        $distribution = $this->analytics()->ageDistribution($this->analyticsFilters());

        return [
            'datasets' => [
                [
                    'label' => 'Anmeldungen',
                    'data' => array_values($distribution),
                    'backgroundColor' => self::PALETTE[0],
                    'borderColor' => self::PALETTE[0],
                ],
            ],
            'labels' => array_keys($distribution),
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
