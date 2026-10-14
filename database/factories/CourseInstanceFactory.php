<?php

namespace Database\Factories;

use App\Enums\CourseInstanceStatus;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\CourseInstance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CourseInstance>
 */
class CourseInstanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'capacity' => 10,
            'waitlist_enabled' => true,
            'status' => CourseInstanceStatus::Published,
            'location' => 'Z-LAB Bruchsal',
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => CourseInstanceStatus::Draft]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => CourseInstanceStatus::Cancelled]);
    }

    /**
     * Adds weekly dates starting at the given moment (default: next week, 16:00–17:30).
     */
    public function withDates(int $count = 1, ?Carbon $firstStart = null): static
    {
        return $this->afterCreating(function (CourseInstance $instance) use ($count, $firstStart): void {
            $start = ($firstStart ?? now()->addWeek()->setTime(16, 0))->copy();

            for ($i = 0; $i < $count; $i++) {
                CourseDate::factory()->for($instance)->create([
                    'starts_at' => $start->copy()->addWeeks($i),
                    'ends_at' => $start->copy()->addWeeks($i)->addMinutes(90),
                ]);
            }
        });
    }
}
