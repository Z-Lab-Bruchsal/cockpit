<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_id' => Registration::factory(),
            'course_instance_id' => fn (array $attributes): int => Registration::query()->findOrFail($attributes['registration_id'])->course_instance_id,
            'kid_name' => fake()->firstName(),
            'status' => EnrollmentStatus::Confirmed,
        ];
    }
}
