<?php

namespace App\Livewire\Homepage;

use App\Enums\TileType;
use App\Filament\Pages\Inline\CreateTileInline;
use App\Filament\Pages\Inline\EditTileInline;
use App\Models\Section;
use App\Models\Tile;
use App\Models\User;
use App\Services\Homepage\GridItem;
use App\Services\Homepage\TileGrid;
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
 * Lets cockpit users arrange all tiles of a section page directly on the homepage: drag to reorder,
 * drag the corner (or use the buttons) to resize, hide, duplicate, delete, edit and add tiles.
 * Every change is saved immediately.
 */
class SectionTileEditor extends Component
{
    #[Locked]
    public int $sectionId;

    public function mount(int $sectionId): void
    {
        $this->sectionId = $sectionId;

        $this->authorizeEditor();
    }

    /**
     * Whether the current user may arrange the tiles of the section.
     */
    public static function canArrange(Section $section): bool
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        return $user !== null && $user->can('reorder', Section::class) && $user->can('update', $section);
    }

    /**
     * Every tile of the page as one item, hidden ones included.
     *
     * @return Collection<int, GridItem>
     */
    #[Computed]
    public function items(): Collection
    {
        return TileGrid::items($this->section(), editing: true);
    }

    /**
     * Handler for wire:sort: moves the tile to the given position among all tiles of the page.
     */
    public function sort(string $uuid, int $position): void
    {
        $this->authorizeEditor();

        DB::transaction(function () use ($uuid, $position): void {
            $uuids = $this->section()->tiles()->lockForUpdate()->pluck('uuid')->all();

            abort_unless(in_array($uuid, $uuids, true), 404);

            $uuids = array_values(array_diff($uuids, [$uuid]));
            array_splice($uuids, max(0, min($position, count($uuids))), 0, [$uuid]);

            foreach ($uuids as $index => $orderedUuid) {
                Tile::query()->where('section_id', $this->sectionId)->where('uuid', $orderedUuid)->update(['sort' => $index + 1]);
            }
        });

        unset($this->items);
    }

    public function resize(string $uuid, int $width, int $height): void
    {
        $this->authorizeEditor();

        Validator::make(
            ['width' => $width, 'height' => $height],
            [
                'width' => ['required', 'integer', Rule::in(array_keys(Tile::WIDTHS))],
                'height' => ['required', 'integer', Rule::in(array_keys(Tile::HEIGHTS))],
            ],
        )->validate();

        $this->tile($uuid)->update(['width' => $width, 'height' => $height]);

        unset($this->items);
    }

    public function toggleVisibility(string $uuid): void
    {
        $this->authorizeEditor();

        $tile = $this->tile($uuid);
        $tile->update(['is_visible' => ! $tile->is_visible]);

        unset($this->items);
    }

    public function duplicate(string $uuid): void
    {
        $this->authorizeEditor();

        $this->tile($uuid)->duplicate();

        unset($this->items);
    }

    public function delete(string $uuid): void
    {
        $this->authorizeEditor();

        $this->tile($uuid)->delete();

        unset($this->items);
    }

    public function render(): View
    {
        return view('livewire.homepage.section-tile-editor', [
            'widths' => array_keys(Tile::WIDTHS),
            'tileTypes' => TileType::cases(),
        ]);
    }

    public function editUrl(Tile $tile): string
    {
        return EditTileInline::getUrl(['tile' => $tile->uuid]);
    }

    public function createUrl(TileType $type): string
    {
        return CreateTileInline::getUrl(['section' => $this->sectionId, 'type' => $type->value]);
    }

    protected function tile(string $uuid): Tile
    {
        return Tile::query()->where('section_id', $this->sectionId)->where('uuid', $uuid)->firstOrFail();
    }

    protected function section(): Section
    {
        return Section::query()->findOrFail($this->sectionId);
    }

    protected function authorizeEditor(): void
    {
        abort_unless(static::canArrange($this->section()), 403);
    }
}
