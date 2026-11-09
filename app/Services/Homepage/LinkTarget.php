<?php

namespace App\Services\Homepage;

use App\Models\Section;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Where a tile or button points to, see LinkTargetFields for the cockpit fields.
 * The data holds `target_type` plus `section_id`, `url` or `anchor` depending on the type.
 */
class LinkTarget
{
    public const SECTION = 'section';

    public const COURSES = 'courses';

    public const CALENDAR = 'calendar';

    public const NEWS = 'news';

    public const URL = 'url';

    public const NONE = 'none';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            static::SECTION => 'Bereich / Seite',
            static::COURSES => 'Kursübersicht',
            static::CALENDAR => 'Kalender',
            static::NEWS => 'News',
            static::URL => 'Eigene Adresse',
            static::NONE => 'Kein Link',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function type(array $data): string
    {
        $type = (string) ($data['target_type'] ?? '');

        if (array_key_exists($type, static::options())) {
            return $type;
        }

        // Legacy data without a type: a section id or url decides.
        return match (true) {
            filled($data['section_id'] ?? null) => static::SECTION,
            filled($data['url'] ?? null) => static::URL,
            default => static::NONE,
        };
    }

    /**
     * The URL to link to, or null if there is nothing (reachable) to link to.
     *
     * @param  array<string, mixed>  $data
     * @param  Collection<int, Section>|null  $sections  Preloaded sections keyed by id, to avoid a query per link.
     */
    public static function resolve(array $data, ?Collection $sections = null): ?string
    {
        $anchor = Str::slug((string) ($data['anchor'] ?? ''));
        $suffix = $anchor !== '' ? '#'.$anchor : '';

        return match (static::type($data)) {
            static::SECTION => ($section = static::section($data, $sections)) !== null && $section->isPubliclyVisible()
                ? $section->url().$suffix
                : null,
            static::COURSES => route('homepage.courses.index').$suffix,
            static::CALENDAR => route('homepage.calendar').$suffix,
            static::NEWS => route('homepage.news.index').$suffix,
            static::URL => Str::sanitizeUrl($data['url'] ?? null, ['http', 'https', 'mailto', 'tel']),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, Section>|null  $sections
     */
    public static function section(array $data, ?Collection $sections = null): ?Section
    {
        if (static::type($data) !== static::SECTION || blank($data['section_id'] ?? null)) {
            return null;
        }

        $id = (int) $data['section_id'];

        return $sections !== null ? $sections->get($id) : Section::query()->find($id);
    }
}
