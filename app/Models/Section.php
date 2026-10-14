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

#[Fillable(['parent_id', 'title', 'slug', 'sort', 'teaser', 'tile_image_path', 'tile_image_alt', 'is_visible', 'show_title', 'show_in_navigation', 'is_standalone', 'layout', 'content'])]
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
        'schulen', 'buchung', 'warenkorb', 'bestaetigen',
        'updateOrderstatusOrdered', 'updateOrderstatusTaken',
    ];

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
     * Child sections shown as tiles, i.e. visible and not only linked in the footer.
     *
     * @return Collection<int, Section>
     */
    public function tileChildren(): Collection
    {
        return $this->visibleChildren->where('is_standalone', false)->values();
    }
}
