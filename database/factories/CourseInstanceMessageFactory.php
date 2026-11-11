<?php

namespace Database\Factories;

use App\Models\CourseInstance;
use App\Models\CourseInstanceMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseInstanceMessage>
 */
class CourseInstanceMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_instance_id' => CourseInstance::factory(),
            'user_id' => User::factory(),
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'recipients' => [
                ['registration_id' => 1, 'email' => fake()->safeEmail(), 'name' => fake()->name()],
            ],
        ];
    }
}
