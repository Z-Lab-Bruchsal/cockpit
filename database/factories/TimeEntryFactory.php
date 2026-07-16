<?php

namespace Database\Factories;

use App\Enums\TimeEntryType;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'user_id' => $user->id,
            'type' => fake()->randomElement(TimeEntryType::cases()),
            'happened_at' => now(),
            'recorded_by_user_id' => $user->id,
        ];
    }
}
