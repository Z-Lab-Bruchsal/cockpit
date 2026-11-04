<?php

namespace Database\Factories;

use App\Models\NewsPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NewsPost>
 */
class NewsPostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(4, true);

        return [
            'title' => Str::ucfirst($title),
            'slug' => Str::slug($title),
            'teaser' => fake()->sentence(),
            'content' => [
                [
                    'type' => 'text',
                    'data' => ['body' => '<p>'.fake()->sentence().'</p>'],
                ],
            ],
            'published_at' => now()->subDays(fake()->numberBetween(1, 60)),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => ['published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => ['published_at' => now()->addWeek()]);
    }
}
