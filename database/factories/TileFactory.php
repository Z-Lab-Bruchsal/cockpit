<?php

namespace Database\Factories;

use App\Enums\CourseAudience;
use App\Enums\TileStyle;
use App\Enums\TileType;
use App\Models\Section;
use App\Models\Tile;
use App\Services\Homepage\LinkTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tile>
 */
class TileFactory extends Factory
{
    /**
     * A content tile without link.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'type' => TileType::Content,
            'is_visible' => true,
            'width' => 2,
            'height' => 1,
            'style' => TileStyle::Standard,
            'heading' => fake()->words(3, true),
            'text' => '<p>'.fake()->sentence().'</p>',
            'link_type' => LinkTarget::NONE,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes): array => ['is_visible' => false]);
    }

    /**
     * A navigation tile linking to the given section, using its tile defaults.
     */
    public function linkingTo(Section $target): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => TileType::Section,
            'style' => null,
            'heading' => null,
            'text' => null,
            'link_type' => LinkTarget::SECTION,
            'link_section_id' => $target->id,
            'width' => $target->tile_width ?: 2,
            'height' => $target->tile_height ?: 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function courses(array $settings = []): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => TileType::Courses,
            'style' => null,
            'heading' => null,
            'text' => null,
            'width' => 6,
            'settings' => [
                'audience' => null,
                'interest_ids' => [],
                'include_course_ids' => [],
                'exclude_course_ids' => [],
                'limit' => 6,
                'item_width' => 2,
                'item_height' => 1,
                'show_all_link' => true,
                ...$settings,
            ],
        ]);
    }

    public function forSchools(): static
    {
        return $this->courses(['audience' => CourseAudience::SchoolClass->value]);
    }

    /**
     * A tile of a block type, e.g. ofType(TileType::Text, ['body' => '<p>…</p>']).
     *
     * @param  array<string, mixed>  $settings
     */
    public function ofType(TileType $type, array $settings = []): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => $type,
            'style' => null,
            'heading' => null,
            'text' => null,
            'width' => $type->defaultWidth(),
            'settings' => $settings,
        ]);
    }

    /**
     * A text tile over the full width.
     */
    public function text(?string $body = null): static
    {
        return $this->ofType(TileType::Text, ['body' => $body ?? '<p>'.fake()->sentence().'</p>']);
    }
}
