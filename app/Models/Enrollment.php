<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One child's seat (or waitlist place) in a course instance.
 * The birthdate entered at checkout is hidden from serialization, like the kid's birthdate.
 */
#[Fillable(['registration_id', 'course_instance_id', 'kid_id', 'kid_name', 'kid_birthdate', 'status', 'expires_at', 'waitlist_position', 'cancelled_at'])]
#[Hidden(['kid_birthdate'])]
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'kid_birthdate' => 'date',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function courseInstance(): BelongsTo
    {
        return $this->belongsTo(CourseInstance::class);
    }

    public function kid(): BelongsTo
    {
        return $this->belongsTo(Kid::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Enrollments that currently occupy a seat.
     *
     * @param  Builder<Enrollment>  $query
     */
    public function scopeOccupyingSeat(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereIn('status', EnrollmentStatus::permanentlyOccupying())
            ->orWhere(fn (Builder $temporary) => $temporary
                ->whereIn('status', EnrollmentStatus::temporarilyOccupying())
                ->where('expires_at', '>', now())));
    }

    /**
     * Enrollments waiting on the waitlist that have not expired.
     *
     * @param  Builder<Enrollment>  $query
     */
    public function scopeOnWaitlist(Builder $query): void
    {
        $query->where('status', EnrollmentStatus::Waitlisted)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function displayKidName(): string
    {
        return $this->kid?->name ?? $this->kid_name ?? 'Platz '.$this->id;
    }

    public function kidAge(?Carbon $at = null): ?int
    {
        if ($this->kid !== null) {
            return $this->kid->age($at);
        }

        return $this->kid_birthdate !== null ? (int) floor($this->kid_birthdate->diffInYears($at ?? now())) : null;
    }
}
