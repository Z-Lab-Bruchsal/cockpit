<?php

namespace Database\Factories;

use App\Models\CourseInstance;
use App\Models\CourseInstanceNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseInstanceNote>
 */
class CourseInstanceNoteFactory extends Factory
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
            'body' => fake()->sentence(),
        ];
    }
}
