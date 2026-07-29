<?php

namespace Database\Factories;

use App\Models\TimeProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeProfile>
 */
class TimeProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Vollzeit 40h', 'Teilzeit 20h', 'Vollzeit 35h']),
            'weekly_hours' => fake()->randomElement([20, 30, 35, 40]),
            'description' => fake()->sentence(),
        ];
    }
}
