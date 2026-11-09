<?php

namespace App\Services\Homepage;

use App\Models\Course;
use App\Models\CourseDate;
use App\Models\CourseInstance;
use App\Models\CourseTemplate;
use App\Models\Interest;
use App\Models\Section;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Previews of courses and sections with the (possibly unsaved) state of a cockpit form.
 * The state is kept in the cache under a random token and rendered with the real homepage views,
 * built into models that are never saved.
 */
class HomepagePreview
{
    public const TYPE_COURSE = 'course';

    public const TYPE_SECTION = 'section';

    public const TYPE_COURSE_INSTANCE = 'course_instance';

    /**
     * Form fields that hold a single image upload.
     *
     * @var list<string>
     */
    protected const IMAGE_FIELDS = ['card_image_path', 'image_path', 'tile_image_path', 'image'];

    /**
     * @param  array<string, mixed>  $rawState  Raw form state, e.g. from `$livewire->form->getRawState()`.
     */
    public static function store(string $type, array $rawState, ?Model $record, int $userId): string
    {
        $token = Str::random(40);

        Cache::put(static::cacheKey($token), [
            'type' => $type,
            'user_id' => $userId,
            'record_id' => $record?->getKey(),
            'state' => static::normalize($rawState),
        ], now()->addMinutes(30));

        return $token;
    }

    /**
     * @return array{type: string, user_id: int, record_id: int|null, state: array<string, mixed>}|null
     */
    public static function find(string $token, int $userId): ?array
    {
        $snapshot = Cache::get(static::cacheKey($token));

        if (! is_array($snapshot) || $snapshot['user_id'] !== $userId) {
            return null;
        }

        return $snapshot;
    }

    /**
     * @param  array{record_id: int|null, state: array<string, mixed>}  $snapshot
     */
    public static function buildCourse(array $snapshot): Course
    {
        $state = $snapshot['state'];
        $course = static::baseModel(Course::class, $snapshot['record_id']);

        $course->forceFill(Arr::except($state, ['interests', 'belts']));

        if (blank($course->slug)) {
            $course->slug = Str::slug((string) $course->name, language: 'de') ?: 'vorschau';
        }

        $course->setRelation('interests', Interest::query()
            ->whereIn('id', array_map('intval', (array) ($state['interests'] ?? [])))
            ->orderBy('sort')
            ->get());
        $course->setRelation('template', filled($course->course_template_id) ? CourseTemplate::query()->find($course->course_template_id) : null);
        $course->load('upcomingInstances.dates');

        return $course;
    }

    /**
     * @param  array{record_id: int|null, state: array<string, mixed>}  $snapshot
     */
    public static function buildCourseInstance(array $snapshot): CourseInstance
    {
        $state = $snapshot['state'];
        $instance = static::baseModel(CourseInstance::class, $snapshot['record_id']);

        $instance->forceFill(Arr::only($state, $instance->getFillable()));

        $course = Course::query()->with(['template', 'interests'])->find($instance->course_id) ?? new Course(['name' => 'Kurs', 'slug' => 'vorschau']);
        $instance->setRelation('course', $course);

        $dates = collect(is_array($state['dates'] ?? null) ? $state['dates'] : [])
            ->filter(fn (mixed $date): bool => is_array($date) && filled($date['starts_at'] ?? null) && filled($date['ends_at'] ?? null))
            ->map(fn (array $date): CourseDate => new CourseDate([
                'starts_at' => Carbon::parse($date['starts_at']),
                'ends_at' => Carbon::parse($date['ends_at']),
                'location' => $date['location'] ?? null,
            ]))
            ->sortBy('starts_at')
            ->values();

        $instance->setRelation('dates', new EloquentCollection($dates->all()));
        $instance->setRelation('firstDate', $dates->first());

        return $instance;
    }

    /**
     * @param  array{record_id: int|null, state: array<string, mixed>}  $snapshot
     */
    public static function buildSection(array $snapshot): Section
    {
        $section = static::baseModel(Section::class, $snapshot['record_id']);

        $section->forceFill($snapshot['state']);

        if (blank($section->slug)) {
            $section->slug = Str::slug((string) $section->title, language: 'de') ?: 'vorschau';
        }

        return $section;
    }

    /**
     * Turns raw Livewire form state into plain, cacheable values as the homepage views expect them:
     * uploads become a single path or URL, rich editor documents become HTML and builder items a list.
     */
    public static function normalize(mixed $value, ?string $key = null): mixed
    {
        if ($value instanceof TemporaryUploadedFile) {
            return static::temporaryUrl($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        if (in_array($key, static::IMAGE_FIELDS, true)) {
            $file = Arr::first($value);

            return $file === null ? null : static::normalize($file);
        }

        if (($value['type'] ?? null) === 'doc' && array_key_exists('content', $value)) {
            return RichContentRenderer::make($value)->toHtml();
        }

        $normalized = [];

        foreach ($value as $childKey => $child) {
            $normalized[$childKey] = static::normalize($child, is_string($childKey) ? $childKey : null);
        }

        if (static::isBuilderState($normalized)) {
            return array_values($normalized);
        }

        return $normalized;
    }

    /**
     * Builder items are keyed by UUIDs and each have a type and data.
     *
     * @param  array<mixed>  $value
     */
    protected static function isBuilderState(array $value): bool
    {
        if ($value === [] || array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_array($item) || ! array_key_exists('type', $item) || ! array_key_exists('data', $item)) {
                return false;
            }
        }

        return true;
    }

    protected static function temporaryUrl(TemporaryUploadedFile $file): ?string
    {
        try {
            return $file->temporaryUrl();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The saved record (so that relations like dates or child sections are read from the database) or a new model.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $class
     * @return TModel
     */
    protected static function baseModel(string $class, ?int $recordId): Model
    {
        return $recordId !== null ? $class::query()->findOrFail($recordId) : new $class;
    }

    protected static function cacheKey(string $token): string
    {
        return 'homepage-preview:'.$token;
    }
}
