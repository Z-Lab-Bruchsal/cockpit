<?php

namespace App\Filament\Widgets\Analytics\Concerns;

use App\Models\User;
use App\Services\Reports\AnalyticsFilters;
use App\Services\Reports\AnalyticsService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Shared behaviour of the analytics widgets: reads the dashboard filters and checks the widget permission.
 */
trait InteractsWithAnalytics
{
    use InteractsWithPageFilters;

    /**
     * Colors for series and slices, in order.
     *
     * @var list<string>
     */
    public const array PALETTE = ['#d6246e', '#2563eb', '#16a34a', '#f59e0b', '#7c3aed', '#0891b2', '#dc2626', '#65a30d', '#db2777', '#475569'];

    public static function canView(): bool
    {
        $user = User::find(filament()->auth()->id());

        return parent::canView()
            && $user !== null
            && $user->can('View:'.class_basename(static::class))
            && static::canViewData($user);
    }

    /**
     * Additional check for widgets showing sensitive data.
     */
    protected static function canViewData(User $user): bool
    {
        return true;
    }

    protected function analyticsFilters(): AnalyticsFilters
    {
        return AnalyticsFilters::fromArray($this->pageFilters);
    }

    protected function analytics(): AnalyticsService
    {
        return app(AnalyticsService::class);
    }

    /**
     * @return list<string>
     */
    protected static function paletteFor(int $count): array
    {
        return array_map(fn (int $index): string => self::PALETTE[$index % count(self::PALETTE)], range(0, max(0, $count - 1)));
    }
}
