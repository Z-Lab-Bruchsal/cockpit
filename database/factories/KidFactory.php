<?php

namespace Database\Factories;

use App\Models\Kid;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kid>
 */
class KidFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'birthdate' => fake()->dateTimeBetween('-14 years', '-7 years')->format('Y-m-d'),
        ];
    }

    public function aged(int $years): static
    {
        return $this->state(fn (array $attributes): array => ['birthdate' => now()->subYears($years)->subMonth()->toDateString()]);
    }
}
