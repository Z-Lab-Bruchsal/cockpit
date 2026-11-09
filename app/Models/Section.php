<?php

namespace App\Models;

use App\Enums\SectionLayout;
use App\Enums\TileStyle;
use App\Services\Homepage\ContentIds;
use App\Services\Homepage\LinkTarget;
use App\Services\Homepage\Tile;
use Database\Factories\SectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['parent_id', 'title', 'slug', 'sort', 'teaser', 'tile_image_path', 'tile_image_alt', 'is_visible', 'show_title', 'show_in_navigation', 'is_standalone', 'is_home', 'layout', 'tile_width', 'tile_height', 'content'])]
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
        'bestellung', 'vorschau', 'team', 'news', 'startseite',
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
            'is_home' => 'boolean',
            'layout' => SectionLayout::class,
            'tile_width' => 'integer',
            'tile_height' => 'integer',
            'content' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Section $section): void {
            $section->content = ContentIds::ensure($section->content);
        });

        static::saved(function (Section $section): void {
            if ($section->is_home && ($section->wasRecentlyCreated || $section->wasChanged('is_home'))) {
                static::query()->whereKeyNot($section->id)->where('is_home', true)->update(['is_home' => false]);
            }
        });
    }

    /**
     * The start page. It is created on first use with a hero tile and the root sections as tiles,
     * like the start page looked before it became editable.
     */
    public static function home(): Section
    {
        $home = static::query()->where('is_home', true)->first();

        if ($home !== null) {
            return $home;
        }

        $settings = CompanySetting::current();
        $content = [[
            'type' => 'tiles',
            'data' => ['tiles' => [[
                'style' => TileStyle::Hero->value,
                'heading' => $settings->homepageTitle(),
                'text' => filled($settings->homepage_tagline) ? '<p>'.e($settings->homepage_tagline).'</p>' : null,
                'target_type' => LinkTarget::NONE,
                'width' => 6,
                'height' => 1,
            ]]],
        ]];

        $roots = static::query()->roots()->where('is_standalone', false)->where('is_home', false)->get();

        if ($roots->isNotEmpty()) {
            $content[] = static::tilesBlockFor($roots);
        }

        return static::query()->create([
            'title' => 'Startseite',
            'slug' => static::query()->where('slug', 'startseite')->exists() ? 'startseite-'.Str::lower(Str::random(6)) : 'startseite',
            'sort' => 0,
            'is_visible' => true,
            'show_title' => false,
            'show_in_navigation' => false,
            'is_standalone' => true,
            'is_home' => true,
            'content' => $content,
        ]);
    }

    /**
     * A tile grid block with one tile per section.
     *
     * @param  iterable<Section>  $sections
     * @return array{type: string, data: array<string, mixed>}
     */
    public static function tilesBlockFor(iterable $sections): array
    {
        $tiles = [];

        foreach ($sections as $section) {
            $tiles[] = Tile::dataForSection($section);
        }

        return ['type' => 'tiles', 'data' => ['tiles' => $tiles]];
    }

    /**
     * Whether the page contains at least one tile grid, i.e. there is something to arrange.
     */
    public function hasTiles(): bool
    {
        return collect($this->content ?? [])->contains(fn (mixed $block): bool => ($block['type'] ?? null) === 'tiles' && ! empty($block['data']['tiles']));
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
        if ($this->is_home) {
            return route('homepage.index');
        }

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
}
