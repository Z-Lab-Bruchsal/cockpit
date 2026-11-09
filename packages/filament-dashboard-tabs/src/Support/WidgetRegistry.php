<?php

namespace AlexanderGabriel\FilamentDashboardTabs\Support;

use AlexanderGabriel\FilamentDashboardTabs\Contracts\HasDashboardLabel;
use AlexanderGabriel\FilamentDashboardTabs\Enums\WidgetWidth;
use AlexanderGabriel\FilamentDashboardTabs\FilamentDashboardTabsPlugin;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Resolves which widgets a user may place on their dashboard tabs.
 */
class WidgetRegistry
{
    public function __construct(protected FilamentDashboardTabsPlugin $plugin) {}

    public static function make(): static
    {
        return new static(FilamentDashboardTabsPlugin::get());
    }

    /**
     * Widgets the current user may place, in registration order.
     *
     * @return list<class-string<Widget>>
     */
    public function available(): array
    {
        return $this->visible($this->plugin->getWidgets() ?? Filament::getWidgets());
    }

    /**
     * Widgets for a user's first tab.
     *
     * @return list<class-string<Widget>>
     */
    public function defaults(): array
    {
        $defaults = $this->plugin->getDefaultWidgets();

        if ($defaults === null) {
            return $this->available();
        }

        return array_values(array_intersect($this->visible($defaults), $this->available()));
    }

    public function isAvailable(string $widget): bool
    {
        return in_array($widget, $this->available(), true);
    }

    /**
     * @return array<class-string<Widget>, string>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->available() as $widget) {
            $options[$widget] = $this->label($widget);
        }

        return $options;
    }

    public function label(string $widget): string
    {
        if (is_subclass_of($widget, HasDashboardLabel::class)) {
            return $widget::getDashboardLabel();
        }

        return Str::of(class_basename($widget))->beforeLast('Widget')->headline()->toString() ?: class_basename($widget);
    }

    /**
     * The width a widget asks for through its own column span.
     *
     * @param  class-string<Widget>  $widget
     */
    public function defaultWidth(string $widget): WidgetWidth
    {
        $property = (new ReflectionClass($widget))->getProperty('columnSpan');

        return WidgetWidth::fromColumnSpan($property->getDefaultValue() ?? 1);
    }

    /**
     * @param  array<class-string<Widget>|WidgetConfiguration>  $widgets
     * @return list<class-string<Widget>>
     */
    protected function visible(array $widgets): array
    {
        $classes = array_map(
            fn (string|WidgetConfiguration $widget): string => $widget instanceof WidgetConfiguration ? $widget->widget : $widget,
            $widgets,
        );

        return array_values(array_unique(array_filter(
            $classes,
            fn (string $widget): bool => class_exists($widget) && is_subclass_of($widget, Widget::class) && $widget::canView(),
        )));
    }
}
