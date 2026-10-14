<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\CourseDate;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'course_date_id' => CourseDate::factory(),
            'attended' => true,
            'checked_at' => now(),
        ];
    }
}
