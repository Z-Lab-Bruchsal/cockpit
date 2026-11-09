<?php

namespace AlexanderGabriel\FilamentDashboardTabs\Database\Factories;

use AlexanderGabriel\FilamentDashboardTabs\Enums\WidgetWidth;
use AlexanderGabriel\FilamentDashboardTabs\Models\DashboardTab;
use AlexanderGabriel\FilamentDashboardTabs\Models\DashboardTabWidget;
use Filament\Widgets\AccountWidget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DashboardTabWidget>
 */
class DashboardTabWidgetFactory extends Factory
{
    protected $model = DashboardTabWidget::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dashboard_tab_id' => DashboardTab::factory(),
            'widget' => AccountWidget::class,
            'width' => WidgetWidth::Full,
            'sort' => fake()->numberBetween(0, 100),
        ];
    }

    public function half(): static
    {
        return $this->state(['width' => WidgetWidth::Half]);
    }
}
