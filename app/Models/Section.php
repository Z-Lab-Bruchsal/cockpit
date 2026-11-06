<?php

namespace App\Models;

use App\Enums\SectionLayout;
use Database\Factories\SectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'title', 'slug', 'sort', 'teaser', 'tile_image_path', 'tile_image_alt', 'is_visible', 'show_title', 'show_in_navigation', 'is_standalone', 'layout', 'tile_width', 'tile_height', 'content'])]
class Section extends Model
{
    /** @use HasFactory<SectionFactory> */
    use HasFactory;

    /**
     * Paths used by the application itself, which a section page must not occupy.
     *
     * @var list<string>
     */
    public const RESERVED_SLUGS = [
        'cockpit', 'api', 'livewire', 'storage', 'build', 'up',
        'kurse', 'kalender', 'konto', 'anmelden', 'abmelden', 'login', 'logout',
        'schulen', 'buchung', 'warenkorb', 'bestaetigen', 'email-aendern', 'kontaktformular', 'anmeldung', 'freigabe',
        'bestellung', 'vorschau', 'team', 'news',
    ];

    /**
     * Tile widths in columns of the 6-column homepage grid: one third, half, two thirds, full.
     *
     * @var array<int, string>
     */
    public const TILE_WIDTHS = [2 => '⅓ Breite', 3 => '½ Breite', 4 => '⅔ Breite', 6 => 'Ganze Breite'];

    /**
     * Tile heights in grid rows.
     *
     * @var array<int, string>
     */
    public const TILE_HEIGHTS = [1 => 'Normal', 2 => 'Doppelt hoch'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'show_title' => 'boolean',
            'show_in_navigation' => 'boolean',
            'is_standalone' => 'boolean',
            'layout' => SectionLayout::class,
            'tile_width' => 'integer',
            'tile_height' => 'integer',
            'content' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Section::class, 'parent_id')->orderBy('sort');
    }

    public function visibleChildren(): HasMany
    {
        return $this->children()->where('is_visible', true);
    }

    /**
     * @param  Builder<Section>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_visible', true);
    }

    /**
     * @param  Builder<Section>  $query
     */
    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id')->orderBy('sort');
    }

    /**
     * Number of ancestors, used for indentation and heading levels.
     */
    public function depth(): int
    {
        $depth = 0;
        $section = $this;

        while ($section->parent_id !== null && $depth < 10) {
            $section = $section->parent;
            $depth++;
        }

        return $depth;
    }

    /**
     * Ids of this section and all of its descendants, to prevent circular parent assignments.
     *
     * @return list<int>
     */
    public function selfAndDescendantIds(): array
    {
        $ids = [$this->id];

        foreach ($this->children as $child) {
            array_push($ids, ...$child->selfAndDescendantIds());
        }

        return $ids;
    }

    /**
     * Every section has its own page; the hierarchy is shown via breadcrumbs.
     */
    public function url(): string
    {
        return route('homepage.section', $this->slug);
    }

    /**
     * Ancestors from the root down to the direct parent.
     *
     * @return list<Section>
     */
    public function ancestors(): array
    {
        $ancestors = [];
        $section = $this->parent;

        while ($section !== null && count($ancestors) < 10) {
            array_unshift($ancestors, $section);
            $section = $section->parent;
        }

        return $ancestors;
    }

    /**
     * A section is only reachable if it and all of its ancestors are visible.
     */
    public function isPubliclyVisible(): bool
    {
        if (! $this->is_visible) {
            return false;
        }

        foreach ($this->ancestors() as $ancestor) {
            if (! $ancestor->is_visible) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ids of all sections depth-first: each parent directly followed by its children, siblings by sort.
     * Sections that cannot be reached from a root (broken parent chain) are appended at the end.
     *
     * @return list<int>
     */
    public static function idsInTreeOrder(): array
    {
        $sections = static::query()
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'parent_id']);

        $childrenByParent = $sections->groupBy(fn (Section $section): string => (string) $section->parent_id);
        $orderedIds = [];

        $appendChildren = function (string $parentKey) use (&$appendChildren, &$orderedIds, $childrenByParent): void {
            foreach ($childrenByParent->get($parentKey, []) as $child) {
                if (isset($orderedIds[$child->id])) {
                    continue;
                }

                $orderedIds[$child->id] = $child->id;
                $appendChildren((string) $child->id);
            }
        };

        $appendChildren('');

        foreach ($sections as $section) {
            $orderedIds[$section->id] ??= $section->id;
        }

        return array_values($orderedIds);
    }

    /**
     * Rewrites the sort values so that sorting all sections flat by sort yields the tree order.
     * The order among siblings is kept, so the homepage does not change.
     */
    public static function renumberInTreeOrder(): void
    {
        $currentSorts = static::query()->pluck('sort', 'id');

        foreach (static::idsInTreeOrder() as $index => $id) {
            if ($currentSorts[$id] !== $index + 1) {
                static::query()->whereKey($id)->update(['sort' => $index + 1]);
            }
        }
    }

    /**
     * Orders a query in tree order, see idsInTreeOrder().
     *
     * @param  Builder<Section>  $query
     * @return Builder<Section>
     */
    public static function orderByTree(Builder $query): Builder
    {
        $ids = static::idsInTreeOrder();

        if ($ids === []) {
            return $query;
        }

        $cases = implode(' ', array_fill(0, count($ids), 'WHEN ? THEN ?'));
        $bindings = [];

        foreach ($ids as $position => $id) {
            array_push($bindings, $id, $position);
        }

        return $query->orderByRaw("CASE {$query->qualifyColumn('id')} {$cases} ELSE ".count($ids).' END', $bindings);
    }

    /**
     * Child sections shown as tiles, i.e. visible and not only linked in the footer.
     *
     * @return Collection<int, Section>
     */
    public function tileChildren(): Collection
    {
        return $this->visibleChildren->where('is_standalone', false)->values();
    }
}
