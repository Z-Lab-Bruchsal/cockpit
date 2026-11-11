<?php

namespace App\Filament\Widgets\Analytics;

use AlexanderGabriel\FilamentDashboardTabs\Contracts\HasDashboardLabel;
use App\Filament\Widgets\Analytics\Concerns\InteractsWithAnalytics;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class ParticipantStatsWidget extends StatsOverviewWidget implements HasDashboardLabel
{
    use InteractsWithAnalytics;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Teilnehmende';

    public static function getDashboardLabel(): string
    {
        return 'Auswertung: Teilnehmende';
    }

    /**
     * @return int|array<string, int|null>|null
     */
    protected function getColumns(): int|array|null
    {
        return ['md' => 3, 'xl' => 5];
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $summary = $this->analytics()->participantSummary($this->analyticsFilters());

        return [
            Stat::make('Kinder', Number::format($summary['kids'], locale: 'de'))
                ->description('verschiedene Kinder mit Teilnahme')
                ->icon(Heroicon::OutlinedFaceSmile),
            Stat::make('Anmeldungen', Number::format($summary['enrollments'], locale: 'de'))
                ->description('bestätigte Kursplätze')
                ->icon(Heroicon::OutlinedTicket),
            Stat::make('Warteliste', Number::format($summary['waitlisted'], locale: 'de'))
                ->color($summary['waitlisted'] > 0 ? 'warning' : 'gray')
                ->icon(Heroicon::OutlinedQueueList),
            Stat::make('Ø Alter', $summary['average_age'] === null ? '–' : Number::format($summary['average_age'], precision: 1, locale: 'de').' Jahre')
                ->description('zu Kursbeginn')
                ->icon(Heroicon::OutlinedCake),
            Stat::make('Schulklassen', Number::format($summary['school_classes'], locale: 'de'))
                ->description(Number::format($summary['students'], locale: 'de').' Schülerinnen und Schüler')
                ->icon(Heroicon::OutlinedBuildingLibrary),
        ];
    }
}
