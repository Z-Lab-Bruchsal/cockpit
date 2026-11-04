<?php

namespace App\Livewire\Homepage;

use App\Models\Section;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Lets cockpit users arrange the tiles of one level (start page or a section page) directly on the homepage:
 * drag to reorder, drag the corner (or use the buttons) to resize. Every change is saved immediately.
 */
class TileEditor extends Component
{
    #[Locked]
    public ?int $parentId = null;

    public function mount(?int $parentId = null): void
    {
        $this->authorizeEditor();

        $this->parentId = $parentId;
    }

    /**
     * Tiles of this level in their current order, i.e. visible sections that are not standalone pages.
     *
     * @return Collection<int, Section>
     */
    #[Computed]
    public function tiles(): Collection
    {
        return Section::query()
            ->visible()
            ->where('parent_id', $this->parentId)
            ->where('is_standalone', false)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    /**
     * Handler for wire:sort. Hidden and standalone siblings keep their sort values,
     * the tiles only swap the sort values they already occupy.
     */
    public function sort(int $id, int $position): void
    {
        $this->authorizeEditor();

        $tiles = $this->tiles();
        $moved = $tiles->firstWhere('id', $id);

        abort_if($moved === null, 404);

        $ordered = $tiles->reject(fn (Section $tile): bool => $tile->id === $id)->values();
        $ordered->splice(max(0, min($position, $ordered->count())), 0, [$moved]);

        $sortValues = $this->distinctSortValues($tiles);

        DB::transaction(function () use ($ordered, $sortValues): void {
            foreach ($ordered as $index => $tile) {
                if ($tile->sort !== $sortValues[$index]) {
                    $tile->update(['sort' => $sortValues[$index]]);
                }
            }
        });

        unset($this->tiles);
    }

    public function resize(int $id, int $width, int $height): void
    {
        $this->authorizeEditor();

        Validator::make(
            ['width' => $width, 'height' => $height],
            [
                'width' => ['required', 'integer', Rule::in(array_keys(Section::TILE_WIDTHS))],
                'height' => ['required', 'integer', Rule::in(array_keys(Section::TILE_HEIGHTS))],
            ],
        )->validate();

        $tile = $this->tiles()->firstWhere('id', $id);

        abort_if($tile === null, 404);
        abort_unless($this->editor()->can('update', $tile), 403);

        $tile->update(['tile_width' => $width, 'tile_height' => $height]);

        unset($this->tiles);
    }

    public function render(): View
    {
        return view('livewire.homepage.tile-editor', [
            'widths' => array_keys(Section::TILE_WIDTHS),
        ]);
    }

    /**
     * The sort values currently used by the tiles, ascending. Duplicates (e.g. all 0 after creating) are spread out
     * so that the new order is unambiguous.
     *
     * @param  Collection<int, Section>  $tiles
     * @return list<int>
     */
    protected function distinctSortValues(Collection $tiles): array
    {
        $values = [];
        $previous = null;

        foreach ($tiles->pluck('sort')->sort()->values() as $value) {
            $value = $previous !== null && $value <= $previous ? $previous + 1 : (int) $value;
            $values[] = $value;
            $previous = $value;
        }

        return $values;
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
        abort_unless($this->editor()->can('reorder', Section::class), 403);
    }
}
