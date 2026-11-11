<?php

namespace App\Filament\Widgets\Analytics;

use AlexanderGabriel\FilamentDashboardTabs\Contracts\HasDashboardLabel;
use App\Filament\Widgets\Analytics\Concerns\InteractsWithAnalytics;
use App\Models\User;
use App\Services\Reports\BookingReportService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Number;

class WorkHoursWidget extends TableWidget implements HasDashboardLabel
{
    use InteractsWithAnalytics;

    protected int|string|array $columnSpan = 'full';

    public static function getDashboardLabel(): string
    {
        return 'Auswertung: Stunden der Übungsleiter';
    }

    protected static function canViewData(User $user): bool
    {
        return $user->can('Worktimes:SupervisorReport');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Stunden der Übungsleiter')
            ->description('Gearbeitete Stunden je Kalenderwoche im Vergleich zu den vereinbarten Wochenstunden')
            ->records(fn (): array => collect($this->analytics()->workHours($this->analyticsFilters()))->keyBy('user_id')->all())
            ->paginated(false)
            ->emptyStateHeading('Keine Mitarbeiter mit vereinbarten Wochenstunden')
            ->emptyStateDescription('Die Wochenstunden werden beim Benutzer hinterlegt.')
            ->columns([
                TextColumn::make('name')
                    ->label('Mitarbeiter')
                    ->weight('medium'),
                TextColumn::make('weekly_hours')
                    ->label('Soll / Woche')
                    ->formatStateUsing(fn (float $state): string => Number::format($state, maxPrecision: 2, locale: 'de').' h'),
                TextColumn::make('current_week_minutes')
                    ->label('Diese Woche')
                    ->formatStateUsing(fn (int $state): string => BookingReportService::formatMinutes($state))
                    ->badge()
                    ->color(fn (array $record): string => $record['current_week_minutes'] > $record['target_minutes'] ? 'danger' : 'success'),
                TextColumn::make('average_week_minutes')
                    ->label('Ø / Woche')
                    ->formatStateUsing(fn (int $state): string => BookingReportService::formatMinutes($state)),
                TextColumn::make('max_week_minutes')
                    ->label('Höchste Woche')
                    ->formatStateUsing(fn (int $state): string => BookingReportService::formatMinutes($state))
                    ->color(fn (array $record): ?string => $record['max_week_minutes'] > $record['target_minutes'] ? 'danger' : null),
                TextColumn::make('weeks_over')
                    ->label('Wochen über Soll')
                    ->formatStateUsing(fn (int $state, array $record): string => $state.' von '.count($record['weeks']))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),
                TextColumn::make('overtime_minutes')
                    ->label('Mehrstunden')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? '+ '.BookingReportService::formatMinutes($state) : '–')
                    ->color(fn (int $state): ?string => $state > 0 ? 'danger' : null),
            ]);
    }
}
