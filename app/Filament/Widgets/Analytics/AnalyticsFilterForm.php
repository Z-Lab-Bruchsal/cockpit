<?php

namespace App\Filament\Widgets\Analytics;

use App\Enums\CourseAudience;
use App\Models\Course;
use App\Models\Interest;
use App\Models\Merchant;
use App\Models\Orderstatus;
use App\Models\User;
use App\Services\Reports\AnalyticsFilters;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Filter components shared by the dashboard tabs and the analytics page; read with AnalyticsFilters::fromArray().
 * Laid out for a schema with three columns, see columns().
 */
class AnalyticsFilterForm
{
    /**
     * @return array<string, int>
     */
    public static function columns(): array
    {
        return ['default' => 1, 'lg' => 3];
    }

    /**
     * @return array<Component>
     */
    public static function components(): array
    {
        $defaults = AnalyticsFilters::defaults();
        $user = User::find(filament()->auth()->id());

        return [
            Fieldset::make('Zeitraum')
                ->schema([
                    DatePicker::make('from')
                        ->label('Von')
                        ->default($defaults['from']),
                    DatePicker::make('to')
                        ->label('Bis')
                        ->default($defaults['to'])
                        ->minDate(fn (Get $get): ?string => $get('from')),
                ])
                ->columns(2)
                ->columnSpan(['default' => 'full', 'lg' => 1]),
            Fieldset::make('Kurse & Kinder')
                ->schema([
                    Select::make('interests')
                        ->label('Interessen')
                        ->options(fn (): array => Interest::query()->where('is_active', true)->orderBy('sort')->orderBy('name')->pluck('name', 'id')->all())
                        ->multiple()
                        ->searchable(),
                    Select::make('courses')
                        ->label('Kurse')
                        ->options(fn (): array => Course::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->multiple()
                        ->searchable(),
                    Select::make('audience')
                        ->label('Zielgruppe')
                        ->options(CourseAudience::class)
                        ->placeholder('Alle'),
                    TextInput::make('age_min')
                        ->label('Alter von')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(120)
                        ->live(onBlur: true),
                    TextInput::make('age_max')
                        ->label('Alter bis')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(120)
                        ->live(onBlur: true),
                ])
                ->columns(['default' => 2, 'xl' => 5])
                ->columnSpan(['default' => 'full', 'lg' => 2]),
            Fieldset::make('Bestellungen')
                ->visible(fn (): bool => (bool) $user?->can('View:OrderCostStatsWidget') || (bool) $user?->can('View:OrderCostsPerMonthChartWidget') || (bool) $user?->can('View:OrderCostsByMerchantChartWidget'))
                ->schema([
                    Select::make('merchants')
                        ->label('Händler')
                        ->options(fn (): array => Merchant::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->multiple()
                        ->searchable(),
                    Select::make('orderstatus')
                        ->label('Bestellstatus')
                        ->options(fn (): array => Orderstatus::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->multiple(),
                ])
                ->columns(2)
                ->columnSpan(['default' => 'full', 'lg' => 2]),
            Fieldset::make('Stunden')
                ->visible(fn (): bool => (bool) $user?->can('Worktimes:SupervisorReport'))
                ->schema([
                    Select::make('users')
                        ->label('Mitarbeiter')
                        ->options(fn (): array => User::query()->whereNotNull('weekly_hours')->orderBy('name')->pluck('name', 'id')->all())
                        ->multiple()
                        ->searchable(),
                ])
                ->columns(1)
                ->columnSpan(['default' => 'full', 'lg' => 1]),
        ];
    }
}
