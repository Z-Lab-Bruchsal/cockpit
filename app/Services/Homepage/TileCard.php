<?php

namespace App\Services\Homepage;

use App\Enums\TileStyle;
use App\Enums\TileType;
use App\Models\Section;
use App\Models\Tile;

/**
 * A card tile (content or section link), ready for rendering.
 * Empty heading, text or image fall back to the tile defaults of the linked section.
 */
final readonly class TileCard
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
     * The card for a tile, or null if it links to a section that is not publicly visible.
     * The linked section should be eager loaded (linkSection.parent).
     * With $evenIfUnreachable such tiles get a card without link instead, e.g. for the editor.
     */
    public static function fromTile(Tile $tile, bool $evenIfUnreachable = false): ?TileCard
    {
        $link = $tile->linkData();
        $section = $link['target_type'] === LinkTarget::SECTION ? $tile->linkSection : null;
        $sections = collect($section !== null ? [$section->id => $section] : []);
        $url = LinkTarget::resolve($link, $sections);

        if ($link['target_type'] === LinkTarget::SECTION && $url === null && ! $evenIfUnreachable) {
            return null;
        }

        $text = filled(strip_tags((string) $tile->text)) ? (string) $tile->text : null;
        $image = filled($tile->image_path) ? (string) $tile->image_path : null;

        return new TileCard(
            id: (string) $tile->uuid,
            style: $tile->style ?? self::sectionStyle($tile->type === TileType::Section ? $section : null),
            eyebrow: filled($tile->eyebrow) ? (string) $tile->eyebrow : null,
            heading: filled($tile->heading) ? (string) $tile->heading : (string) $section?->title,
            textHtml: $text ?? ($section?->teaser ? '<p>'.e($section->teaser).'</p>' : null),
            imagePath: $image ?? $section?->tile_image_path,
            imageAlt: $image !== null ? $tile->image_alt : $section?->tile_image_alt,
            url: $url,
            width: self::width($tile->width),
            height: self::height($tile->height),
        );
    }

    /**
     * The tile a section gets by default when a tile links to it, e.g. for the cockpit preview.
     */
    public static function forSection(Section $section): TileCard
    {
        return new TileCard(
            id: 'section-'.($section->id ?? 'new'),
            style: self::sectionStyle($section),
            eyebrow: null,
            heading: (string) $section->title,
            textHtml: $section->teaser ? '<p>'.e($section->teaser).'</p>' : null,
            imagePath: $section->tile_image_path,
            imageAlt: $section->tile_image_alt,
            url: null,
            width: self::width($section->tile_width),
            height: self::height($section->tile_height),
        );
    }

    public static function width(mixed $width): int
    {
        return array_key_exists((int) $width, Tile::WIDTHS) ? (int) $width : 2;
    }

    public static function height(mixed $height): int
    {
        return array_key_exists((int) $height, Tile::HEIGHTS) ? (int) $height : 1;
    }

    /**
     * Tiles without an own style take the tile colour of the section they link to.
     */
    protected static function sectionStyle(?Section $section): TileStyle
    {
        return $section?->layout?->value === TileStyle::Highlighted->value ? TileStyle::Highlighted : TileStyle::Standard;
    }
}
