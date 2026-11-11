<?php

namespace App\Filament\Widgets\Analytics;

use AlexanderGabriel\FilamentDashboardTabs\Contracts\HasDashboardLabel;
use App\Filament\Widgets\Analytics\Concerns\InteractsWithAnalytics;
use App\Models\Order;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class OrderCostStatsWidget extends StatsOverviewWidget implements HasDashboardLabel
{
    use InteractsWithAnalytics;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Kosten aus Bestellungen';

    public static function getDashboardLabel(): string
    {
        return 'Auswertung: Bestellkosten';
    }

    protected static function canViewData(User $user): bool
    {
        return $user->can('viewAny', Order::class);
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $summary = $this->analytics()->orderSummary($this->analyticsFilters());

        return [
            Stat::make('Summe', Number::currency($summary['total'], 'EUR', 'de'))
                ->description('bestellt im Zeitraum')
                ->icon(Heroicon::OutlinedBanknotes),
            Stat::make('Positionen', Number::format($summary['count'], locale: 'de'))
                ->icon(Heroicon::OutlinedShoppingBag),
            Stat::make('Ø je Position', $summary['average'] === null ? '–' : Number::currency($summary['average'], 'EUR', 'de'))
                ->icon(Heroicon::OutlinedCalculator),
            Stat::make('Ohne Preis', Number::format($summary['without_price'], locale: 'de'))
                ->description($summary['without_price'] > 0 ? 'Preis nachtragen' : 'alle Preise erfasst')
                ->color($summary['without_price'] > 0 ? 'warning' : 'gray')
                ->icon(Heroicon::OutlinedExclamationTriangle),
        ];
    }
}
