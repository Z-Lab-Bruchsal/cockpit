<?php

namespace AlexanderGabriel\FilamentMailTestMode;

use AlexanderGabriel\FilamentMailTestMode\Pages\MailTestModePage;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use UnitEnum;

class FilamentMailTestModePlugin implements Plugin
{
    use EvaluatesClosures;

    protected bool|Closure $isAuthorized = true;

    protected bool $hasBanner = true;

    protected string|UnitEnum|null $navigationGroup = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'mail-test-mode';
    }

    /**
     * Who may see the page and switch the test mode.
     */
    public function authorize(bool|Closure $condition): static
    {
        $this->isAuthorized = $condition;

        return $this;
    }

    public function isAuthorized(): bool
    {
        return (bool) $this->evaluate($this->isAuthorized);
    }

    /**
     * Show a warning bar on every panel page while the test mode is active.
     */
    public function banner(bool $condition = true): static
    {
        $this->hasBanner = $condition;

        return $this;
    }

    public function navigationGroup(string|UnitEnum|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        return $this->navigationGroup;
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            MailTestModePage::class,
        ]);

        if ($this->hasBanner) {
            $panel->renderHook(PanelsRenderHook::BODY_START, fn (): ?View => $this->renderBanner());
        }
    }

    public function boot(Panel $panel): void {}

    protected function renderBanner(): ?View
    {
        $mailTestMode = app(MailTestMode::class);

        if (! $mailTestMode->isEnabled()) {
            return null;
        }

        return view('filament-mail-test-mode::banner', [
            'recipient' => $mailTestMode->recipient(),
            'pageUrl' => $this->isAuthorized() ? MailTestModePage::getUrl() : null,
        ]);
    }
}
