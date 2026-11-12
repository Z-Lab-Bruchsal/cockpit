<?php

namespace Database\Factories;

use App\Enums\TeamArea;
use App\Models\TeamProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamProfile>
 */
class TeamProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'team_visible' => true,
            'team_area' => TeamArea::Jugendleitung,
            'team_role' => fake()->jobTitle(),
            'team_bio' => fake()->sentence(12),
        ];
    }
}
