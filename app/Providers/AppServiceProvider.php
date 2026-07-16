<?php

namespace App\Providers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\Column;
use Filament\Tables\Table;
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
                ->defaultTimeDisplayFormat('H:i');
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
