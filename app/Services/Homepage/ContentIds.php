<?php

namespace App\Services\Homepage;

use Illuminate\Support\Str;

/**
 * Gives every content block (and every tile of a tile grid) a stable, unique id.
 * The ids address single blocks and tiles when arranging, editing or styling them on the homepage.
 * Missing ids are added, duplicated ids (e.g. after cloning a block in the cockpit) are replaced.
 */
class ContentIds
{
    /**
     * @param  array<int, mixed>|null  $content
     * @return array<int, mixed>|null
     */
    public static function ensure(?array $content): ?array
    {
        if ($content === null) {
            return null;
        }

        $seen = [];

        foreach ($content as $index => $block) {
            if (! is_array($block)) {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $data['block_id'] = static::unique($data['block_id'] ?? null, $seen);

            if (($block['type'] ?? null) === 'tiles') {
                $tiles = [];

                foreach (array_values(is_array($data['tiles'] ?? null) ? $data['tiles'] : []) as $tile) {
                    if (is_array($tile)) {
                        $tile['id'] = static::unique($tile['id'] ?? null, $seen);
                        $tiles[] = $tile;
                    }
                }

                $data['tiles'] = $tiles;
            }

            $block['data'] = $data;
            $content[$index] = $block;
        }

        return $content;
    }

    /**
     * @param  array<string, true>  $seen
     */
    protected static function unique(mixed $id, array &$seen): string
    {
        $id = is_string($id) && $id !== '' && ! isset($seen[$id]) ? $id : (string) Str::uuid();
        $seen[$id] = true;

        return $id;
    }

    /**
     * Index of the block with the given id, or null.
     *
     * @param  array<int, mixed>|null  $content
     */
    public static function blockIndex(?array $content, string $blockId): ?int
    {
        foreach ($content ?? [] as $index => $block) {
            if (($block['data']['block_id'] ?? null) === $blockId) {
                return $index;
            }
        }

        return null;
    }
}
