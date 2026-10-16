<?php

namespace Database\Factories;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Todo>
 */
class TodoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'content' => fake()->paragraph(),
            'user_id' => User::factory(),
        ];
    }

    public function individualCompletion(): static
    {
        return $this->state(['individual_completion' => true]);
    }
}
