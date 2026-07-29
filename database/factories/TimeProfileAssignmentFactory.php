<?php

namespace Database\Factories;

use App\Models\TimeProfile;
use App\Models\TimeProfileAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeProfileAssignment>
 */
class TimeProfileAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'time_profile_id' => TimeProfile::factory(),
            'effective_from' => now()->startOfMonth()->toDateString(),
            'effective_to' => null,
        ];
    }
}
