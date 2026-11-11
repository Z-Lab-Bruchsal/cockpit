<?php

namespace App\Services\Homepage;

use App\Enums\CourseAudience;
use App\Enums\TileType;
use App\Models\Course;
use App\Models\Interest;
use App\Models\Section;
use App\Models\Tile;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Builds the cells of the homepage grid of a section from its tiles.
 */
class TileGrid
{
    public const MAX_COURSES = 48;

    /**
     * Grid items of the page in order. Hidden tiles and tiles linking to hidden sections are left out,
     * a courses tile becomes one item per matching course.
     * In the editor every tile is exactly one item, including hidden ones, so that each can be arranged.
     *
     * @return Collection<int, GridItem>
     */
    public static function items(Section $section, bool $editing = false): Collection
    {
        $tiles = $section->tiles()
            ->when(! $editing, fn ($query) => $query->visible())
            ->with('linkSection.parent')
            ->get();

        return $tiles
            ->flatMap(fn (Tile $tile): array => static::itemsFor($tile, $editing))
            ->values();
    }

    /**
     * @return list<GridItem>
     */
    public static function itemsFor(Tile $tile, bool $editing = false): array
    {
        $width = TileCard::width($tile->width);
        $height = TileCard::height($tile->height);

        if ($tile->type === null) {
            return [];
        }

        if ($tile->type->usesCard()) {
            $card = TileCard::fromTile($tile, evenIfUnreachable: $editing);

            return $card === null ? [] : [new GridItem($tile->uuid, $tile, 'card', $width, $height, ['card' => $card])];
        }

        if ($tile->type === TileType::Courses) {
            $courses = static::courses($tile);

            if ($editing) {
                return [new GridItem($tile->uuid, $tile, 'courses-placeholder', $width, $height, ['courses' => $courses])];
            }

            return static::courseItems($tile, $courses);
        }

        return [new GridItem($tile->uuid, $tile, 'block', $width, $height, [
            'type' => $tile->type->value,
            'data' => $tile->settings ?? [],
        ])];
    }

    /**
     * Courses of a courses tile: the fixed courses in their order, then the courses matching the filter
     * (next date first), without excluded ones, up to the limit. Only published courses.
     * Without filter, fixed courses mean "only these"; without both all courses match.
     *
     * @return EloquentCollection<int, Course>
     */
    public static function courses(Tile $tile): EloquentCollection
    {
        $audience = CourseAudience::tryFrom((string) $tile->setting('audience'));
        $interestIds = static::ids($tile->setting('interest_ids'));
        $includedIds = static::ids($tile->setting('include_course_ids'));
        $excludedIds = static::ids($tile->setting('exclude_course_ids'));
        $limit = max(1, min(static::MAX_COURSES, (int) $tile->setting('limit', 6)));

        $included = $includedIds === []
            ? new EloquentCollection
            : Course::query()
                ->forHomepage()
                ->whereIn('courses.id', array_diff($includedIds, $excludedIds))
                ->get()
                ->sortBy(fn (Course $course): int => (int) array_search($course->id, $includedIds, true))
                ->values();

        $hasFilter = $audience !== null || $interestIds !== [];
        $remaining = $limit - $included->count();

        if ($remaining <= 0 || ($includedIds !== [] && ! $hasFilter)) {
            return $included->take($limit)->values();
        }

        $matching = Course::query()
            ->forHomepage(audience: $audience, interestIds: $interestIds)
            ->whereNotIn('courses.id', [...$includedIds, ...$excludedIds])
            ->limit($remaining)
            ->get();

        return $included->concat($matching)->values();
    }

    /**
     * Link to the course overview with the filter of a courses tile, as far as the overview supports it.
     */
    public static function allCoursesUrl(Tile $tile): string
    {
        $interestIds = static::ids($tile->setting('interest_ids'));
        $interestSlug = count($interestIds) === 1 ? Interest::query()->whereKey($interestIds[0])->value('slug') : null;

        return route('homepage.courses.index', array_filter([
            'zielgruppe' => CourseAudience::tryFrom((string) $tile->setting('audience'))?->value,
            'interesse' => $interestSlug,
        ]));
    }

    /**
     * @param  EloquentCollection<int, Course>  $courses
     * @return list<GridItem>
     */
    protected static function courseItems(Tile $tile, EloquentCollection $courses): array
    {
        if ($courses->isEmpty()) {
            return [new GridItem($tile->uuid, $tile, 'courses-empty', 6, 1)];
        }

        $width = TileCard::width($tile->setting('item_width', 2));
        $height = TileCard::height($tile->setting('item_height', 1));

        $items = $courses
            ->map(fn (Course $course): GridItem => new GridItem($tile->uuid.':'.$course->id, $tile, 'course', $width, $height, ['course' => $course]))
            ->all();

        if ($tile->setting('show_all_link', true)) {
            $items[] = new GridItem($tile->uuid.':all', $tile, 'courses-all-link', 6, 1, ['url' => static::allCoursesUrl($tile)]);
        }

        return $items;
    }

    /**
     * @return list<int>
     */
    protected static function ids(mixed $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
    }
}
