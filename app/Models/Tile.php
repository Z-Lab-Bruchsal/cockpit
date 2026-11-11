<?php

namespace App\Models;

use App\Enums\CourseAudience;
use App\Enums\TileStyle;
use App\Enums\TileType;
use App\Services\Homepage\LinkTarget;
use Database\Factories\TileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One tile on a section page. The tiles of a section, ordered by sort, fill the 6-column homepage grid.
 * The uuid addresses the tile on the homepage (arranging, inline editing, design element styles).
 */
#[Fillable(['section_id', 'type', 'sort', 'is_visible', 'width', 'height', 'style', 'eyebrow', 'heading', 'text', 'image_path', 'image_alt', 'link_type', 'link_section_id', 'link_url', 'link_anchor', 'settings'])]
class Tile extends Model
{
    /** @use HasFactory<TileFactory> */
    use HasFactory;

    /**
     * Widths in columns of the 6-column homepage grid: one third, half, two thirds, full.
     *
     * @var array<int, string>
     */
    public const WIDTHS = [2 => '⅓ Breite', 3 => '½ Breite', 4 => '⅔ Breite', 6 => 'Ganze Breite'];

    /**
     * Heights in grid rows.
     *
     * @var array<int, string>
     */
    public const HEIGHTS = [1 => 'Normal', 2 => 'Doppelt hoch'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TileType::class,
            'style' => TileStyle::class,
            'sort' => 'integer',
            'is_visible' => 'boolean',
            'width' => 'integer',
            'height' => 'integer',
            'link_section_id' => 'integer',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Tile $tile): void {
            if (blank($tile->uuid) || static::query()->where('uuid', $tile->uuid)->exists()) {
                $tile->uuid = (string) Str::uuid();
            }

            if (! $tile->isDirty('sort')) {
                $tile->sort = (int) static::query()->where('section_id', $tile->section_id)->max('sort') + 1;
            }
        });
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function linkSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'link_section_id');
    }

    /**
     * @param  Builder<Tile>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_visible', true);
    }

    /**
     * The link target in the shape LinkTarget expects.
     *
     * @return array{target_type: ?string, section_id: ?int, url: ?string, anchor: ?string}
     */
    public function linkData(): array
    {
        return [
            'target_type' => $this->type === TileType::Section ? LinkTarget::SECTION : ($this->link_type ?? LinkTarget::NONE),
            'section_id' => $this->link_section_id,
            'url' => $this->link_url,
            'anchor' => $this->link_anchor,
        ];
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }

    /**
     * A copy right behind this tile, with a new uuid.
     */
    public function duplicate(): Tile
    {
        return DB::transaction(function (): Tile {
            static::query()
                ->where('section_id', $this->section_id)
                ->where('sort', '>', $this->sort)
                ->increment('sort');

            $copy = $this->replicate(['uuid']);
            $copy->sort = $this->sort + 1;
            $copy->save();

            return $copy;
        });
    }

    /**
     * Short description for lists in the cockpit, e.g. "Kurse · Schulklassen · Informatik".
     */
    public function label(): string
    {
        $heading = filled($this->heading) ? $this->heading : null;

        return match ($this->type) {
            TileType::Section => $heading ?? $this->linkSection?->title ?? 'Bereich fehlt',
            TileType::Content => $heading ?? 'Kachel ohne Überschrift',
            TileType::Courses => $this->coursesLabel(),
            TileType::Text, TileType::Free, TileType::TextImage => Str::limit(trim(strip_tags((string) $this->setting('body'))), 60) ?: $this->type->getLabel(),
            TileType::Html => Str::limit(trim(strip_tags((string) $this->setting('html'))), 60) ?: $this->type->getLabel(),
            TileType::Button => (string) ($this->setting('label') ?: $this->type->getLabel()),
            TileType::CallToAction => (string) ($this->setting('heading') ?: $this->type->getLabel()),
            default => $this->type?->getLabel() ?? 'Kachel',
        };
    }

    protected function coursesLabel(): string
    {
        $parts = ['Kurse'];
        $audience = CourseAudience::tryFrom((string) $this->setting('audience'));

        if ($audience !== null) {
            $parts[] = $audience->getLabel();
        }

        $interestIds = array_map('intval', (array) $this->setting('interest_ids', []));

        if ($interestIds !== []) {
            $parts[] = Interest::query()->whereIn('id', $interestIds)->orderBy('sort')->pluck('name')->implode(', ');
        }

        $included = count((array) $this->setting('include_course_ids', []));

        if ($included > 0) {
            $parts[] = $included === 1 ? '1 fest gewählter Kurs' : "{$included} fest gewählte Kurse";
        }

        if (count($parts) === 1) {
            $parts[] = 'alle';
        }

        return implode(' · ', $parts);
    }
}
