<?php

namespace App\Livewire\Homepage;

use App\Models\Section;
use App\Models\User;
use App\Services\Homepage\ContentIds;
use App\Services\Homepage\Tile;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Lets cockpit users arrange the tiles of one tile grid block directly on the homepage:
 * drag to reorder, drag the corner (or use the buttons) to resize. Every change is saved immediately.
 */
class TileEditor extends Component
{
    #[Locked]
    public int $sectionId;

    #[Locked]
    public string $blockId;

    public function mount(int $sectionId, string $blockId): void
    {
        $this->sectionId = $sectionId;
        $this->blockId = $blockId;

        $this->authorizeEditor();
    }

    /**
     * Tiles of this block in their current order, as rendered on the homepage.
     *
     * @return Collection<int, Tile>
     */
    #[Computed]
    public function tiles(): Collection
    {
        return Tile::collect($this->rawTiles($this->section()));
    }

    /**
     * Handler for wire:sort. Tiles that are not rendered (e.g. linking to a hidden section) keep their position
     * among the rendered ones as far as possible.
     */
    public function sort(string $id, int $position): void
    {
        $this->authorizeEditor();

        $this->updateTiles(function (array $tiles) use ($id, $position): array {
            $visibleIds = $this->tiles()->pluck('id')->all();
            $movedIndex = $this->indexOf($tiles, $id);
            $moved = $tiles[$movedIndex];

            array_splice($tiles, $movedIndex, 1);

            $remainingVisibleIds = array_values(array_diff($visibleIds, [$id]));
            $position = max(0, min($position, count($remainingVisibleIds)));
            $insertAt = $position < count($remainingVisibleIds)
                ? $this->indexOf($tiles, $remainingVisibleIds[$position])
                : (count($remainingVisibleIds) > 0 ? $this->indexOf($tiles, end($remainingVisibleIds)) + 1 : count($tiles));

            array_splice($tiles, $insertAt, 0, [$moved]);

            return $tiles;
        });
    }

    public function resize(string $id, int $width, int $height): void
    {
        $this->authorizeEditor();

        Validator::make(
            ['width' => $width, 'height' => $height],
            [
                'width' => ['required', 'integer', Rule::in(array_keys(Section::TILE_WIDTHS))],
                'height' => ['required', 'integer', Rule::in(array_keys(Section::TILE_HEIGHTS))],
            ],
        )->validate();

        $this->updateTiles(function (array $tiles) use ($id, $width, $height): array {
            $index = $this->indexOf($tiles, $id);
            $tiles[$index]['width'] = $width;
            $tiles[$index]['height'] = $height;

            return $tiles;
        });
    }

    public function render(): View
    {
        return view('livewire.homepage.tile-editor', [
            'widths' => array_keys(Section::TILE_WIDTHS),
        ]);
    }

    /**
     * @param  callable(list<array<string, mixed>>): list<array<string, mixed>>  $change
     */
    protected function updateTiles(callable $change): void
    {
        DB::transaction(function () use ($change): void {
            $section = Section::query()->lockForUpdate()->findOrFail($this->sectionId);
            $content = $section->content ?? [];
            $blockIndex = ContentIds::blockIndex($content, $this->blockId);

            abort_if($blockIndex === null, 404);

            $content[$blockIndex]['data']['tiles'] = array_values($change($this->rawTiles($section)));

            $section->update(['content' => $content]);
        });

        unset($this->tiles);
    }

    /**
     * @param  list<array<string, mixed>>  $tiles
     */
    protected function indexOf(array $tiles, string $id): int
    {
        foreach ($tiles as $index => $tile) {
            if (($tile['id'] ?? null) === $id) {
                return $index;
            }
        }

        abort(404);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function rawTiles(Section $section): array
    {
        $blockIndex = ContentIds::blockIndex($section->content, $this->blockId);

        abort_if($blockIndex === null, 404);

        return array_values(array_filter($section->content[$blockIndex]['data']['tiles'] ?? [], 'is_array'));
    }

    protected function section(): Section
    {
        return Section::query()->findOrFail($this->sectionId);
    }

    protected function editor(): User
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        abort_if($user === null, 403);

        return $user;
    }

    protected function authorizeEditor(): void
    {
        $editor = $this->editor();

        abort_unless($editor->can('reorder', Section::class) && $editor->can('update', $this->section()), 403);
    }
}
