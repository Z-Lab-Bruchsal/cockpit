<?php

namespace App\Filament\Widgets\Analytics;

use AlexanderGabriel\FilamentDashboardTabs\Contracts\HasDashboardLabel;
use App\Filament\Widgets\Analytics\Concerns\InteractsWithAnalytics;
use App\Models\User;
use Filament\Widgets\ChartWidget;

class WorkHoursPerWeekChartWidget extends ChartWidget implements HasDashboardLabel
{
    use InteractsWithAnalytics;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Stunden pro Woche';

    protected ?string $description = 'Balken: gearbeitete Stunden · gestrichelt: vereinbarte Wochenstunden';

    protected ?string $maxHeight = '320px';

    public static function getDashboardLabel(): string
    {
        return 'Auswertung: Stunden pro Woche';
    }

    protected static function canViewData(User $user): bool
    {
        return $user->can('Worktimes:SupervisorReport');
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
        $filters = $this->analyticsFilters();
        $weeks = array_keys($this->analytics()->weeks($filters));
        $datasets = [];

        foreach ($this->analytics()->workHours($filters) as $index => $row) {
            $color = self::PALETTE[$index % count(self::PALETTE)];

            $datasets[] = [
                'type' => 'bar',
                'label' => $row['name'],
                'data' => array_map(fn (int $minutes): float => round($minutes / 60, 2), array_values($row['weeks'])),
                'backgroundColor' => $color,
                'borderColor' => $color,
                'order' => 2,
            ];

            $datasets[] = [
                'type' => 'line',
                'label' => 'Soll '.$row['name'],
                'data' => array_fill(0, count($weeks), $row['weekly_hours']),
                'borderColor' => $color,
                'borderDash' => [6, 4],
                'borderWidth' => 2,
                'pointRadius' => 0,
                'fill' => false,
                'order' => 1,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $weeks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => ['y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'Stunden']]],
        ];
    }
}
