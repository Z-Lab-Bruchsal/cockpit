<?php

namespace AlexanderGabriel\FilamentDashboardTabs;

use AlexanderGabriel\FilamentDashboardTabs\Pages\Dashboard;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class FilamentDashboardTabsPlugin implements Plugin
{
    /**
     * @var array<class-string<Widget>|WidgetConfiguration>|null
     */
    protected ?array $widgets = null;

    /**
     * @var array<class-string<Widget>|WidgetConfiguration>|null
     */
    protected ?array $defaultWidgets = null;

    protected ?int $maxTabs = null;

    /**
     * @var class-string<Dashboard>
     */
    protected string $page = Dashboard::class;

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
        return 'filament-dashboard-tabs';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([$this->page]);
    }

    public function boot(Panel $panel): void {}

    /**
     * Restrict the widgets users can pick. Defaults to all widgets registered on the panel.
     *
     * @param  array<class-string<Widget>|WidgetConfiguration>  $widgets
     */
    public function widgets(array $widgets): static
    {
        $this->widgets = $widgets;

        return $this;
    }

    /**
     * @return array<class-string<Widget>|WidgetConfiguration>|null
     */
    public function getWidgets(): ?array
    {
        return $this->widgets;
    }

    /**
     * Widgets placed on a user's first tab. Defaults to all available widgets.
     *
     * @param  array<class-string<Widget>|WidgetConfiguration>  $widgets
     */
    public function defaultWidgets(array $widgets): static
    {
        $this->defaultWidgets = $widgets;

        return $this;
    }

    /**
     * @return array<class-string<Widget>|WidgetConfiguration>|null
     */
    public function getDefaultWidgets(): ?array
    {
        return $this->defaultWidgets;
    }

    public function maxTabs(?int $maxTabs): static
    {
        $this->maxTabs = $maxTabs;

        return $this;
    }

    public function getMaxTabs(): ?int
    {
        return $this->maxTabs;
    }

    /**
     * Use a custom subclass of the dashboard page.
     *
     * @param  class-string<Dashboard>  $page
     */
    public function page(string $page): static
    {
        $this->page = $page;

        return $this;
    }
}
