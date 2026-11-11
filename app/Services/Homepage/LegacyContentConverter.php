<?php

namespace App\Services\Homepage;

use App\Enums\TileType;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Converts between the former content blocks of a section (JSON in sections.content) and tile rows.
 * Plain arrays only, so that migrations can use it without models.
 */
class LegacyContentConverter
{
    /**
     * Tile rows (attributes of the tiles table, without section_id) for the content blocks of a section, in order.
     * A tile grid block becomes one row per tile, every other block one full-width row of the same type.
     *
     * @param  array<int, mixed>|null  $content
     * @return list<array<string, mixed>>
     */
    public static function toTiles(?array $content): array
    {
        $rows = [];

        foreach ($content ?? [] as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            if ($type === 'tiles') {
                foreach ($data['tiles'] ?? [] as $item) {
                    if (is_array($item)) {
                        $rows[] = static::fromGridTile($item);
                    }
                }

                continue;
            }

            if ($type === 'course_list') {
                $rows[] = static::row(TileType::Courses, $data['block_id'] ?? null, [
                    'settings' => [
                        'audience' => $data['audience'] ?? null,
                        'interest_ids' => array_values(array_map('intval', (array) ($data['interest_ids'] ?? []))),
                        'include_course_ids' => [],
                        'exclude_course_ids' => [],
                        'limit' => (int) ($data['limit'] ?? 6),
                        'item_width' => 2,
                        'item_height' => 1,
                        'show_all_link' => (bool) ($data['show_all_link'] ?? true),
                    ],
                ]);

                continue;
            }

            $tileType = TileType::tryFrom($type);

            if ($tileType === null || ! $tileType->usesBlockFields()) {
                continue;
            }

            $rows[] = static::row($tileType, $data['block_id'] ?? null, [
                'settings' => Arr::except($data, ['block_id']),
            ]);
        }

        foreach ($rows as $index => $row) {
            $rows[$index]['sort'] = $index + 1;
        }

        return $rows;
    }

    /**
     * The content blocks for tile rows, the reverse of toTiles(). Consecutive card tiles form one tile grid block.
     *
     * @param  iterable<array<string, mixed>>  $tiles  Rows ordered by sort; settings may still be JSON.
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public static function toContent(iterable $tiles): array
    {
        $content = [];
        $grid = null;

        foreach ($tiles as $tile) {
            $type = TileType::tryFrom((string) ($tile['type'] ?? ''));
            $settings = is_string($tile['settings'] ?? null) ? (json_decode($tile['settings'], true) ?: []) : ($tile['settings'] ?? []);

            if ($type === null) {
                continue;
            }

            if ($type->usesCard()) {
                $grid ??= ['type' => 'tiles', 'data' => ['block_id' => (string) Str::uuid(), 'tiles' => []]];
                $grid['data']['tiles'][] = [
                    'id' => $tile['uuid'] ?? (string) Str::uuid(),
                    'style' => $tile['style'] ?? 'standard',
                    'eyebrow' => $tile['eyebrow'] ?? null,
                    'heading' => $tile['heading'] ?? null,
                    'text' => $tile['text'] ?? null,
                    'image' => $tile['image_path'] ?? null,
                    'alt' => $tile['image_alt'] ?? null,
                    'target_type' => $type === TileType::Section ? LinkTarget::SECTION : ($tile['link_type'] ?? LinkTarget::NONE),
                    'section_id' => $tile['link_section_id'] ?? null,
                    'url' => $tile['link_url'] ?? null,
                    'anchor' => $tile['link_anchor'] ?? null,
                    'width' => (int) ($tile['width'] ?? 2),
                    'height' => (int) ($tile['height'] ?? 1),
                ];

                continue;
            }

            if ($grid !== null) {
                $content[] = $grid;
                $grid = null;
            }

            $content[] = $type === TileType::Courses
                ? ['type' => 'course_list', 'data' => [
                    'block_id' => $tile['uuid'] ?? (string) Str::uuid(),
                    ...Arr::only($settings, ['audience', 'interest_ids', 'limit', 'show_all_link']),
                ]]
                : ['type' => $type->value, 'data' => ['block_id' => $tile['uuid'] ?? (string) Str::uuid(), ...$settings]];
        }

        if ($grid !== null) {
            $content[] = $grid;
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected static function fromGridTile(array $item): array
    {
        $linkType = LinkTarget::type($item);
        $image = filled($item['image'] ?? null) ? (string) $item['image'] : null;
        $hasOwnContent = filled($item['heading'] ?? null)
            || filled(strip_tags((string) ($item['text'] ?? '')))
            || filled($item['eyebrow'] ?? null)
            || $image !== null;
        $type = $linkType === LinkTarget::SECTION && ! $hasOwnContent ? TileType::Section : TileType::Content;
        $width = (int) ($item['width'] ?? 2);

        return static::row($type, $item['id'] ?? null, [
            'width' => in_array($width, [2, 3, 4, 6], true) ? $width : 2,
            'height' => (int) ($item['height'] ?? 1) === 2 ? 2 : 1,
            'style' => filled($item['style'] ?? null) ? (string) $item['style'] : null,
            'eyebrow' => filled($item['eyebrow'] ?? null) ? (string) $item['eyebrow'] : null,
            'heading' => filled($item['heading'] ?? null) ? (string) $item['heading'] : null,
            'text' => filled(strip_tags((string) ($item['text'] ?? ''))) ? (string) $item['text'] : null,
            'image_path' => $image,
            'image_alt' => $image !== null && filled($item['alt'] ?? null) ? (string) $item['alt'] : null,
            'link_type' => $linkType,
            'link_section_id' => $linkType === LinkTarget::SECTION && filled($item['section_id'] ?? null) ? (int) $item['section_id'] : null,
            'link_url' => $linkType === LinkTarget::URL && filled($item['url'] ?? null) ? (string) $item['url'] : null,
            'link_anchor' => filled($item['anchor'] ?? null) ? (string) $item['anchor'] : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected static function row(TileType $type, mixed $uuid, array $attributes): array
    {
        return [
            'uuid' => is_string($uuid) && Str::isUuid($uuid) ? $uuid : (string) Str::uuid(),
            'type' => $type->value,
            'is_visible' => true,
            'width' => $type->defaultWidth() === 6 ? 6 : 2,
            'height' => 1,
            'style' => null,
            'eyebrow' => null,
            'heading' => null,
            'text' => null,
            'image_path' => null,
            'image_alt' => null,
            'link_type' => null,
            'link_section_id' => null,
            'link_url' => null,
            'link_anchor' => null,
            'settings' => null,
            ...$attributes,
        ];
    }
}
