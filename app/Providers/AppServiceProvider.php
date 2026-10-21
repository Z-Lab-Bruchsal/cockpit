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
use Illuminate\Support\Str;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Illuminate\Support\Facades\URL;

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
        URL::forceRootUrl('https://hp-reboot.z-lab-bruchsal.de/');
        URL::forceScheme('https');

        // Magic links: a few per address and per visitor, so the form cannot be used to flood inboxes.
        RateLimiter::for('magic-link', function (Request $request): array {
            return [
                Limit::perMinutes(10, 3)->by('magic-link-email:'.Str::lower((string) $request->input('email'))),
                Limit::perMinute(10)->by('magic-link-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('contact-form', fn (Request $request): Limit => Limit::perHour(5)->by('contact-form:'.$request->ip()));

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

        Filament::serving(function () {
            Filament::registerNavigationGroups([
                NavigationGroup::make()->label('Homepage'),
                NavigationGroup::make()->label('Kurse und Kinder'),
                NavigationGroup::make()->label('Kontakte'),
                NavigationGroup::make()->label('Verwaltung')->collapsed(),
                NavigationGroup::make()->label('Zeiterfassung')->collapsed()
            ]);
        });

    }
}
