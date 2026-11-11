<?php

namespace App\Providers\Filament;

use AlexanderGabriel\FilamentDashboardTabs\FilamentDashboardTabsPlugin;
use AlexanderGabriel\FilamentDashboardTabs\Pages\Dashboard;
use AlexanderGabriel\FilamentMailTestMode\FilamentMailTestModePlugin;
use AlexanderGabriel\FilamentOauth2\FilamentOauth2Plugin;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\Bookings\Widgets\TimeClockWidget;
use App\Filament\Resources\Orders\Widgets\MyOrdersWidget;
use App\Filament\Resources\Todos\Widgets\MyTodosWidget;
use App\Models\CompanySetting;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('cockpit')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->spa()
            ->unsavedChangesAlerts()
            ->passwordReset()
            ->databaseNotifications()
            ->profile()
            ->maxContentWidth(Width::Full)
            ->font(
                'Bunny',
                provider: LocalFontProvider::class,
            )
            ->colors([
                'primary' => Color::Amber,
            ])
            ->colors(fn (): array => CompanySetting::cockpitColors())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                TimeClockWidget::class,
                MyTodosWidget::class,
                MyOrdersWidget::class,
                // FilamentInfoWidget::class,
            ])
            ->renderHook(
                PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE,
                fn (): View => view('filament.dashboard.time-clock-bar'),
                scopes: Dashboard::class,
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make()->navigationGroup('Zugänge'),
                FilamentDashboardTabsPlugin::make(),
                FilamentMailTestModePlugin::make()
                    ->cluster(SettingsCluster::class)
                    ->navigationGroup('Allgemein')
                    ->authorize(fn (): bool => (bool) filament()->auth()->user()?->can('View:MailTestModePage')),
                //                FilamentOauth2Plugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
