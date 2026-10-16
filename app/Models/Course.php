<?php

namespace App\Models;

use App\Enums\CourseAudience;
use App\Enums\CourseInstanceStatus;
use App\Enums\SchoolCourseFormat;
use App\Enums\SchoolType;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'duration',
        'course_template_id',
        'slug',
        'teaser',
        'card_image_path',
        'card_image_alt',
        'image_path',
        'image_alt',
        'description',
        'audience',
        'min_age',
        'max_age',
        'default_capacity',
        'default_waitlist_enabled',
        'is_published',
        'responsible_user_id',
        'waitlist_offer_hours',
        'school_format',
        'school_reminders_enabled',
        'teacher_reminder_days',
        'staff_reminder_days',
        'school_reschedule_deadline_days',
        'school_types',
        'min_grade',
        'max_grade',
        'max_class_size',
    ];

    protected static function booted(): void
    {
        static::saving(function (Course $course): void {
            if (blank($course->slug)) {
                $course->slug = static::uniqueSlug($course->name, $course->id);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => CourseAudience::class,
            'school_format' => SchoolCourseFormat::class,
            'school_types' => AsEnumCollection::of(SchoolType::class),
            'school_reminders_enabled' => 'boolean',
            'default_waitlist_enabled' => 'boolean',
            'is_published' => 'boolean',
            'next_starts_at' => 'datetime',
        ];
    }

    public function belts(): BelongsToMany
    {
        return $this->belongsToMany(Belt::class);
    }

    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CourseTemplate::class, 'course_template_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(CourseInstance::class);
    }

    /**
     * Published instances that have not ended yet, earliest first.
     */
    public function upcomingInstances(): HasMany
    {
        return $this->instances()
            ->published()
            ->upcoming()
            ->orderByFirstDate();
    }

    /**
     * @param  Builder<Course>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Published courses for the homepage, optionally filtered, with upcoming courses first.
     *
     * @param  Builder<Course>  $query
     * @param  list<int>  $interestIds  courses having at least one of these interests
     */
    public function scopeForHomepage(Builder $query, ?string $interestSlug = null, ?int $age = null, ?CourseAudience $audience = null, array $interestIds = []): void
    {
        $query
            ->published()
            ->when($interestSlug, fn (Builder $query) => $query->whereHas('interests', fn (Builder $interests) => $interests->where('slug', $interestSlug)))
            ->when($interestIds, fn (Builder $query) => $query->whereHas('interests', fn (Builder $interests) => $interests->whereIn('interests.id', $interestIds)))
            ->when($age !== null, fn (Builder $query) => $query
                ->where(fn (Builder $min) => $min->whereNull('min_age')->orWhere('min_age', '<=', $age))
                ->where(fn (Builder $max) => $max->whereNull('max_age')->orWhere('max_age', '>=', $age)))
            ->when($audience, fn (Builder $query) => $query->where('audience', $audience))
            ->select('courses.*')
            ->addSelect(['next_starts_at' => CourseDate::query()
                ->selectRaw('min(course_dates.starts_at)')
                ->join('course_instances', 'course_instances.id', '=', 'course_dates.course_instance_id')
                ->whereColumn('course_instances.course_id', 'courses.id')
                ->where('course_instances.status', CourseInstanceStatus::Published)
                ->where('course_dates.starts_at', '>=', now()),
            ])
            ->orderByRaw('next_starts_at is null')
            ->orderBy('next_starts_at')
            ->orderBy('name')
            ->with(['interests', 'template', 'upcomingInstances.firstDate']);
    }

    public function isForSchoolClasses(): bool
    {
        return $this->audience === CourseAudience::SchoolClass;
    }

    /**
     * Where the school visit takes place; school class courses without a setting take place in the Z-LAB.
     */
    public function effectiveSchoolFormat(): SchoolCourseFormat
    {
        return $this->school_format ?? SchoolCourseFormat::AtZLab;
    }

    public function effectiveTemplate(): CourseTemplate
    {
        return $this->template ?? CourseTemplate::fallback();
    }

    /**
     * Small image for the course card: own card image, else the large image, else the template's fallback.
     *
     * @return array{url: string, alt: string}|null
     */
    public function cardImage(): ?array
    {
        $template = $this->effectiveTemplate();

        return match (true) {
            filled($this->card_image_path) => static::image($this->card_image_path, $this->card_image_alt),
            filled($this->image_path) => static::image($this->image_path, $this->image_alt),
            filled($template->default_card_image_path) => static::image($template->default_card_image_path, $template->default_card_image_alt),
            default => null,
        };
    }

    /**
     * Large image for the detail page: own image, else the template's fallback.
     *
     * @return array{url: string, alt: string}|null
     */
    public function detailImage(): ?array
    {
        $template = $this->effectiveTemplate();

        return match (true) {
            filled($this->image_path) => static::image($this->image_path, $this->image_alt),
            filled($template->default_image_path) => static::image($template->default_image_path, $template->default_image_alt),
            default => null,
        };
    }

    /**
     * @return array{url: string, alt: string}
     */
    protected static function image(string $path, ?string $alt): array
    {
        return ['url' => Storage::disk('public')->url($path), 'alt' => (string) $alt];
    }

    /**
     * e.g. "8–12 Jahre", "ab 10 Jahren" or null if no age limits are set.
     */
    public function ageRangeLabel(): ?string
    {
        return match (true) {
            $this->min_age !== null && $this->max_age !== null => "{$this->min_age}–{$this->max_age} Jahre",
            $this->min_age !== null => "ab {$this->min_age} Jahren",
            $this->max_age !== null => "bis {$this->max_age} Jahre",
            default => null,
        };
    }

    /**
     * e.g. "Klasse 3–4", "ab Klasse 5" or null if no grade limits are set.
     */
    public function gradeRangeLabel(): ?string
    {
        return match (true) {
            $this->min_grade !== null && $this->max_grade !== null && $this->min_grade === $this->max_grade => "Klasse {$this->min_grade}",
            $this->min_grade !== null && $this->max_grade !== null => "Klasse {$this->min_grade}–{$this->max_grade}",
            $this->min_grade !== null => "ab Klasse {$this->min_grade}",
            $this->max_grade !== null => "bis Klasse {$this->max_grade}",
            default => null,
        };
    }

    /**
     * School types the course is meant for; empty means all.
     *
     * @return list<SchoolType>
     */
    public function allowedSchoolTypes(): array
    {
        $types = $this->school_types?->all() ?? [];

        return $types === [] ? SchoolType::cases() : array_values($types);
    }

    /**
     * Grades a class may be in to book the course.
     *
     * @return list<int>
     */
    public function allowedGrades(): array
    {
        return range($this->min_grade ?? 1, $this->max_grade ?? 13);
    }

    /**
     * Who the course is for: grades and school types for school classes, else the age range.
     */
    public function audienceLabel(): ?string
    {
        if (! $this->isForSchoolClasses()) {
            return $this->ageRangeLabel();
        }

        $schoolTypes = $this->school_types?->isNotEmpty()
            ? $this->school_types->map(fn (SchoolType $type): string => $type->getLabel())->implode(', ')
            : null;

        return collect([$this->gradeRangeLabel(), $schoolTypes])->filter()->implode(' · ') ?: null;
    }

    public function url(): string
    {
        return route('homepage.courses.show', $this->slug);
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name, language: 'de') ?: 'kurs';
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
