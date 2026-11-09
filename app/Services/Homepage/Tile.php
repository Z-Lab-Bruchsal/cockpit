<?php

namespace App\Services\Homepage;

use App\Enums\TileStyle;
use App\Models\Section;
use Illuminate\Support\Collection;

/**
 * One tile of a tile grid block, ready for rendering.
 * Empty heading, text or image fall back to the tile defaults of the linked section.
 */
final readonly class Tile
{
    public function __construct(
        public string $id,
        public TileStyle $style,
        public ?string $eyebrow,
        public string $heading,
        public ?string $textHtml,
        public ?string $imagePath,
        public ?string $imageAlt,
        public ?string $url,
        public int $width,
        public int $height,
    ) {}

    /**
     * Tiles of a tile grid block. Tiles linking to a section that is not publicly visible are left out.
     *
     * @param  array<int, mixed>  $items
     * @return Collection<int, Tile>
     */
    public static function collect(array $items): Collection
    {
        $items = array_values(array_filter($items, 'is_array'));

        $sectionIds = collect($items)
            ->filter(fn (array $item): bool => LinkTarget::type($item) === LinkTarget::SECTION)
            ->pluck('section_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique();

        $sections = $sectionIds->isEmpty()
            ? collect()
            : Section::query()->with('parent')->whereIn('id', $sectionIds)->get()->keyBy('id');

        return collect($items)
            ->map(fn (array $item): ?Tile => self::fromArray($item, $sections))
            ->filter()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Collection<int, Section>|null  $sections
     */
    public static function fromArray(array $item, ?Collection $sections = null): ?Tile
    {
        $section = LinkTarget::section($item, $sections);
        $url = LinkTarget::resolve($item, $sections);

        if (LinkTarget::type($item) === LinkTarget::SECTION && $url === null) {
            return null;
        }

        $text = filled(strip_tags((string) ($item['text'] ?? ''))) ? (string) $item['text'] : null;
        $image = filled($item['image'] ?? null) ? (string) $item['image'] : null;

        return new Tile(
            id: (string) ($item['id'] ?? ''),
            style: TileStyle::tryFrom((string) ($item['style'] ?? '')) ?? TileStyle::Standard,
            eyebrow: filled($item['eyebrow'] ?? null) ? (string) $item['eyebrow'] : null,
            heading: filled($item['heading'] ?? null) ? (string) $item['heading'] : (string) $section?->title,
            textHtml: $text ?? ($section?->teaser ? '<p>'.e($section->teaser).'</p>' : null),
            imagePath: $image ?? $section?->tile_image_path,
            imageAlt: $image !== null ? ($item['alt'] ?? null) : $section?->tile_image_alt,
            url: $url,
            width: array_key_exists((int) ($item['width'] ?? 0), Section::TILE_WIDTHS) ? (int) $item['width'] : 2,
            height: array_key_exists((int) ($item['height'] ?? 0), Section::TILE_HEIGHTS) ? (int) $item['height'] : 1,
        );
    }

    /**
     * The tile a section gets by default when a tile links to it, e.g. for the cockpit preview.
     */
    public static function forSection(Section $section): Tile
    {
        return new Tile(
            id: 'section-'.($section->id ?? 'new'),
            style: $section->layout?->value === TileStyle::Highlighted->value ? TileStyle::Highlighted : TileStyle::Standard,
            eyebrow: null,
            heading: (string) $section->title,
            textHtml: $section->teaser ? '<p>'.e($section->teaser).'</p>' : null,
            imagePath: $section->tile_image_path,
            imageAlt: $section->tile_image_alt,
            url: null,
            width: array_key_exists((int) $section->tile_width, Section::TILE_WIDTHS) ? (int) $section->tile_width : 2,
            height: array_key_exists((int) $section->tile_height, Section::TILE_HEIGHTS) ? (int) $section->tile_height : 1,
        );
    }

    /**
     * Tile data that links to the given section, using its tile defaults.
     *
     * @return array<string, mixed>
     */
    public static function dataForSection(Section $section): array
    {
        return [
            'style' => $section->layout?->value === TileStyle::Highlighted->value ? TileStyle::Highlighted->value : TileStyle::Standard->value,
            'eyebrow' => null,
            'heading' => null,
            'text' => null,
            'image' => null,
            'alt' => null,
            'target_type' => LinkTarget::SECTION,
            'section_id' => $section->id,
            'url' => null,
            'anchor' => null,
            'width' => $section->tile_width ?: 2,
            'height' => $section->tile_height ?: 1,
        ];
    }
}
