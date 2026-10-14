<?php

namespace Database\Factories;

use App\Enums\CourseCardStyle;
use App\Enums\CourseDetailLayout;
use App\Models\CourseTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseTemplate>
 */
class CourseTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'is_default' => false,
            'card_style' => CourseCardStyle::ImageTop,
            'detail_layout' => CourseDetailLayout::HeroImage,
            'accent_color' => null,
            'show_age_range' => true,
            'show_interests' => true,
            'show_free_seats' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes): array => ['is_default' => true]);
    }
}
