<?php

namespace AlexanderGabriel\FilamentMailTestMode;

use AlexanderGabriel\FilamentMailTestMode\Listeners\RedirectMailInTestMode;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentMailTestModeServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-mail-test-mode';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasMigration('create_mail_test_mode_settings_table')
            ->runsMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(MailTestMode::class);
    }

    public function packageBooted(): void
    {
        Event::listen(MessageSending::class, RedirectMailInTestMode::class);
    }
}
