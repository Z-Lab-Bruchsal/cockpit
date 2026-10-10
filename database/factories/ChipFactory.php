<?php

namespace Database\Factories;

use App\Models\Chip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chip>
 */
class ChipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uid' => strtoupper(fake()->unique()->regexify('[0-9a-f]{8}')),
            'user_id' => User::factory(),
            'is_active' => true,
        ];
    }

    public function unassigned(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
