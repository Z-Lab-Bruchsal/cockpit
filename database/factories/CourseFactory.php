<?php

namespace Database\Factories;

use App\Enums\CourseAudience;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'duration' => 90,
            'teaser' => fake()->sentence(),
            'description' => '<p>'.fake()->paragraph().'</p>',
            'audience' => CourseAudience::Kids,
            'min_age' => 8,
            'max_age' => 14,
            'default_capacity' => 10,
            'default_waitlist_enabled' => true,
            'is_published' => true,
            'responsible_user_id' => User::factory(),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes): array => ['is_published' => false]);
    }

    public function forSchoolClasses(): static
    {
        return $this->state(fn (array $attributes): array => [
            'audience' => CourseAudience::SchoolClass,
            'default_capacity' => 1,
            'default_waitlist_enabled' => false,
        ]);
    }
}
