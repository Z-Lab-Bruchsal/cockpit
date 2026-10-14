<?php

namespace App\Providers;

use App\Models\CompanySetting;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\Column;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        CompanySetting::applyToConfig();

        RateLimiter::for('terminal', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->attributes->get('terminal')?->id ?? $request->ip());
        });

        Table::configureUsing(function (Table $table): void {
            $table
                ->reorderableColumns()
                ->persistColumnSearchesInSession()
                ->persistColumnsInSession()
                ->persistFiltersInSession()
                ->persistSearchInSession()
                ->persistSortInSession()
                ->selectable()
                ->defaultPaginationPageOption(50)
                ->defaultDateDisplayFormat('d.m.Y')
                ->defaultDateTimeDisplayFormat('d.m.Y H:i')
                ->defaultTimeDisplayFormat('H:i')
                // Row actions as icon buttons in front of the columns, with their label as tooltip.
                ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
                ->modifyUngroupedRecordActionsUsing(function (Action $action): void {
                    $action->iconButton();

                    if (blank($action->getTooltip())) {
                        $action->tooltip(fn (Action $action): string|Htmlable|null => $action->getLabel());
                    }
                });
        });
        Column::configureUsing(function (Column $column): void {
            $column->toggleable();
        });

        // German date format for every DatePicker/DateTimePicker in the app.
        // DatePicker extends DateTimePicker, so both registrations are needed:
        // the DateTimePicker one applies first, then the DatePicker one overrides
        // the format for date-only fields.
        DateTimePicker::configureUsing(function (DateTimePicker $component): void {
            $component
                ->native(false)
                ->displayFormat('d.m.Y H:i');
        });
        DatePicker::configureUsing(function (DatePicker $component): void {
            $component
                ->native(false)
                ->displayFormat('d.m.Y');
        });
    }
}
