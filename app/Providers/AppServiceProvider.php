<?php

namespace App\Providers;

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
                ->defaultPaginationPageOption(50);
        });
        Column::configureUsing(function (Column $column): void {
            $column->toggleable();
        });
    }
}
