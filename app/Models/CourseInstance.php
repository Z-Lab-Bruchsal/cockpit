<?php

namespace App\Models;

use App\Enums\CourseAvailability;
use App\Enums\CourseInstanceStatus;
use Database\Factories\CourseInstanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

#[Fillable(['course_id', 'title', 'capacity', 'waitlist_enabled', 'waitlist_limit', 'status', 'registration_opens_at', 'registration_closes_at', 'location', 'notes'])]
class CourseInstance extends Model
{
    /** @use HasFactory<CourseInstanceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CourseInstanceStatus::class,
            'waitlist_enabled' => 'boolean',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function dates(): HasMany
    {
        return $this->hasMany(CourseDate::class)->orderBy('starts_at');
    }

    public function firstDate(): HasOne
    {
        return $this->hasOne(CourseDate::class)->oldestOfMany('starts_at');
    }

    public function lastDate(): HasOne
    {
        return $this->hasOne(CourseDate::class)->latestOfMany('ends_at');
    }

    /**
     * @param  Builder<CourseInstance>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', CourseInstanceStatus::Published);
    }

    /**
     * Instances with at least one date that has not ended yet.
     *
     * @param  Builder<CourseInstance>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->whereHas('dates', fn (Builder $dates) => $dates->where('ends_at', '>=', now()));
    }

    /**
     * @param  Builder<CourseInstance>  $query
     */
    public function scopeOrderByFirstDate(Builder $query): void
    {
        $query->orderBy(
            CourseDate::query()
                ->select('starts_at')
                ->whereColumn('course_dates.course_instance_id', 'course_instances.id')
                ->orderBy('starts_at')
                ->limit(1)
        );
    }

    public function displayTitle(): string
    {
        return $this->title ?: (string) $this->course?->name;
    }

    /**
     * Seats occupied by registrations. Registrations are introduced with the booking flow;
     * until then every seat is free.
     */
    public function takenSeats(): int
    {
        return 0;
    }

    public function freeSeats(): int
    {
        return max(0, $this->capacity - $this->takenSeats());
    }

    /**
     * Registrations close when explicitly configured, otherwise when the first date starts.
     */
    public function registrationClosesAt(): ?Carbon
    {
        return $this->registration_closes_at ?? $this->firstDate?->starts_at;
    }

    public function availability(): CourseAvailability
    {
        if ($this->status === CourseInstanceStatus::Cancelled) {
            return CourseAvailability::Cancelled;
        }

        if ($this->status !== CourseInstanceStatus::Published) {
            return CourseAvailability::Closed;
        }

        if ($this->registration_opens_at?->isFuture()) {
            return CourseAvailability::NotYetOpen;
        }

        if ($this->registrationClosesAt()?->isPast()) {
            return CourseAvailability::Closed;
        }

        $freeSeats = $this->freeSeats();

        if ($freeSeats === 0) {
            return $this->waitlist_enabled ? CourseAvailability::Waitlist : CourseAvailability::Full;
        }

        if ($freeSeats <= max(1, (int) ceil($this->capacity * 0.2))) {
            return CourseAvailability::FewSeatsLeft;
        }

        return CourseAvailability::Available;
    }
}
