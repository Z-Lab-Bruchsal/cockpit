<?php

namespace Database\Factories;

use App\Enums\SectionLayout;
use App\Models\Section;
use App\Services\Homepage\LegacyContentConverter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(2, true);

        return [
            'title' => Str::title($title),
            'slug' => Str::slug($title),
            'sort' => 0,
            'teaser' => fake()->sentence(),
            'is_visible' => true,
            'show_title' => true,
            'show_in_navigation' => true,
            'is_standalone' => false,
            'layout' => SectionLayout::Standard,
            'tile_width' => 2,
            'tile_height' => 1,
        ];
    }

    /**
     * Gives the section tiles made from content blocks in the former sections.content format,
     * e.g. [['type' => 'text', 'data' => ['body' => '<p>…</p>']]], see LegacyContentConverter.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public function withContent(array $blocks): static
    {
        return $this->afterCreating(function (Section $section) use ($blocks): void {
            foreach (LegacyContentConverter::toTiles($blocks) as $row) {
                $section->tiles()->create($row);
            }
        });
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes): array => ['is_visible' => false]);
    }

    public function standalone(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_standalone' => true,
            'show_in_navigation' => false,
        ]);
    }

    public function childOf(Section $parent): static
    {
        return $this->state(fn (array $attributes): array => ['parent_id' => $parent->id]);
    }
}
