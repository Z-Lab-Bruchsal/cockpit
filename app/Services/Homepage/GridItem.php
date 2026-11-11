<?php

namespace App\Services\Homepage;

use App\Models\Tile;

/**
 * One cell of the homepage grid. Usually one tile, but a courses tile becomes one item per course.
 * Rendered by resources/views/components/homepage/tiles/{view}.blade.php inside the tile frame.
 */
final readonly class GridItem
{
    /**
     * @param  array<string, mixed>  $props  Variables for the view.
     */
    public function __construct(
        public string $key,
        public Tile $tile,
        public string $view,
        public int $width,
        public int $height,
        public array $props = [],
    ) {}

    /**
     * Whether the item is drawn as a card, so it gets the minimum height of a grid row.
     */
    public function isCard(): bool
    {
        return in_array($this->view, ['card', 'course', 'courses-placeholder'], true);
    }
}
