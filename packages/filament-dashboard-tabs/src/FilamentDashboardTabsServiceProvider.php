<?php

namespace AlexanderGabriel\FilamentDashboardTabs;

use Illuminate\Support\ServiceProvider;

class FilamentDashboardTabsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filament-dashboard-tabs.php', 'filament-dashboard-tabs');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-dashboard-tabs');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-dashboard-tabs');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/filament-dashboard-tabs.php' => config_path('filament-dashboard-tabs.php'),
            ], 'filament-dashboard-tabs-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/filament-dashboard-tabs'),
            ], 'filament-dashboard-tabs-views');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/filament-dashboard-tabs'),
            ], 'filament-dashboard-tabs-translations');
        }
    }
}
