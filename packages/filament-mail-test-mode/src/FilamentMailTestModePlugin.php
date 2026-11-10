<?php

namespace AlexanderGabriel\FilamentMailTestMode;

use AlexanderGabriel\FilamentMailTestMode\Pages\MailTestModePage;
use Closure;
use Filament\Clusters\Cluster;
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

    /**
     * @var class-string<Cluster>|null
     */
    protected ?string $cluster = null;

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

    /**
     * Places the page inside a cluster, e.g. the settings area of the panel.
     *
     * @param  class-string<Cluster>|null  $cluster
     */
    public function cluster(?string $cluster): static
    {
        $this->cluster = $cluster;

        return $this;
    }

    /**
     * @return class-string<Cluster>|null
     */
    public function getCluster(): ?string
    {
        return $this->cluster;
    }

    public function register(Panel $panel): void
    {
        // Routes are registered without a current panel, so the page cannot ask the plugin itself.
        MailTestModePage::useCluster($this->cluster);

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
