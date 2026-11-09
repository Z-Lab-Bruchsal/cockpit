<?php

namespace AlexanderGabriel\FilamentDashboardTabs\Database\Factories;

use AlexanderGabriel\FilamentDashboardTabs\Models\DashboardTab;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DashboardTab>
 */
class DashboardTabFactory extends Factory
{
    protected $model = DashboardTab::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => DashboardTab::userModel()::factory(),
            'name' => fake()->words(2, true),
            'sort' => fake()->numberBetween(0, 100),
        ];
    }
}
