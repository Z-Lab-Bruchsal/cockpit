<?php

namespace Database\Factories;

use App\Enums\RegistrationSource;
use App\Enums\RegistrationStatus;
use App\Models\CourseInstance;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
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
            'status' => RegistrationStatus::Confirmed,
            'source' => RegistrationSource::Homepage,
            'email' => fake()->safeEmail(),
            'contact_name' => fake()->name(),
            'confirmed_at' => now(),
        ];
    }
}
