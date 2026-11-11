<?php

namespace App\Filament\Widgets\Analytics;

use AlexanderGabriel\FilamentDashboardTabs\Contracts\HasDashboardLabel;
use App\Filament\Widgets\Analytics\Concerns\InteractsWithAnalytics;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class CourseStatsWidget extends StatsOverviewWidget implements HasDashboardLabel
{
    use InteractsWithAnalytics;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Kurse';

    public static function getDashboardLabel(): string
    {
        return 'Auswertung: Kurse';
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $summary = $this->analytics()->courseSummary($this->analyticsFilters());

        return [
            Stat::make('Kurse', Number::format($summary['courses'], locale: 'de'))
                ->description('mit mindestens einer Durchführung')
                ->icon(Heroicon::OutlinedAcademicCap),
            Stat::make('Durchführungen', Number::format($summary['instances'], locale: 'de'))
                ->description($summary['cancelled'] > 0 ? $summary['cancelled'].' abgesagt' : 'keine abgesagt')
                ->descriptionColor($summary['cancelled'] > 0 ? 'danger' : 'gray')
                ->icon(Heroicon::OutlinedCalendarDays),
            Stat::make('Belegte Plätze', Number::format($summary['occupied'], locale: 'de').' / '.Number::format($summary['capacity'], locale: 'de'))
                ->description('Kinderkurse, bestätigte Anmeldungen')
                ->icon(Heroicon::OutlinedUserGroup),
            Stat::make('Auslastung', $summary['utilization'] === null ? '–' : Number::format($summary['utilization'], precision: 1, locale: 'de').' %')
                ->color(match (true) {
                    $summary['utilization'] === null => 'gray',
                    $summary['utilization'] >= 80 => 'success',
                    $summary['utilization'] >= 50 => 'warning',
                    default => 'danger',
                })
                ->icon(Heroicon::OutlinedChartPie),
        ];
    }
}
