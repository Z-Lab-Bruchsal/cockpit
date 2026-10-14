<?php

namespace Database\Factories;

use App\Models\CourseDate;
use App\Models\CourseInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseDate>
 */
class CourseDateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addWeek()->setTime(16, 0);

        return [
            'course_instance_id' => CourseInstance::factory(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(90),
        ];
    }
}
