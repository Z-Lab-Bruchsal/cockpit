<?php

namespace App\Models;

use App\Enums\CourseAvailability;
use App\Enums\CourseInstanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\SchoolVenue;
use Database\Factories\CourseInstanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

#[Fillable(['course_id', 'title', 'capacity', 'waitlist_enabled', 'waitlist_limit', 'status', 'registration_opens_at', 'registration_closes_at', 'location', 'notes', 'responsible_user_id', 'waitlist_offer_hours', 'school_reminders_enabled', 'teacher_reminder_days', 'staff_reminder_days', 'school_reschedule_deadline_days', 'max_class_size'])]
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
            'school_reminders_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // A school class course is booked by exactly one class at a time and has no waitlist.
        static::saving(function (CourseInstance $instance): void {
            if ($instance->course?->isForSchoolClasses()) {
                $instance->capacity = 1;
                $instance->waitlist_enabled = false;
                $instance->waitlist_limit = null;
            }
        });
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function dates(): HasMany
    {
        return $this->hasMany(CourseDate::class)->orderBy('starts_at');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * Who decides about sibling and waitlist approvals: set on the instance, else on the course.
     */
    public function effectiveResponsibleUser(): ?User
    {
        return $this->responsibleUser ?? $this->course?->responsibleUser;
    }

    /**
     * Address for approval requests. Every course has a responsible person; the company contact address
     * is only a safety net in case that person's user account was deleted.
     */
    public function approvalRecipient(): ?string
    {
        return $this->effectiveResponsibleUser()?->email
            ?? CompanySetting::current()->contact_email
            ?? config('mail.from.address');
    }

    /**
     * Hours a family moving up from the waitlist has to accept the seat: instance, else course, else company setting.
     */
    public function waitlistOfferHours(): int
    {
        return $this->waitlist_offer_hours
            ?? $this->course?->waitlist_offer_hours
            ?? CompanySetting::current()->waitlist_offer_hours;
    }

    /**
     * Whether teacher and staff reminders are sent for school visits: instance, else course, else company setting.
     */
    public function schoolRemindersEnabled(): bool
    {
        return $this->school_reminders_enabled
            ?? $this->course?->school_reminders_enabled
            ?? CompanySetting::current()->school_reminders_enabled;
    }

    /**
     * Days before the first date the teacher is reminded: instance, else course, else company setting.
     */
    public function teacherReminderDays(): int
    {
        return $this->teacher_reminder_days
            ?? $this->course?->teacher_reminder_days
            ?? CompanySetting::current()->teacher_reminder_days;
    }

    /**
     * Days before the first date the course lead is reminded: instance, else course, else company setting.
     */
    public function staffReminderDays(): int
    {
        return $this->staff_reminder_days
            ?? $this->course?->staff_reminder_days
            ?? CompanySetting::current()->staff_reminder_days;
    }

    /**
     * Largest class allowed to book: instance, else course; null means no limit.
     */
    public function maxClassSize(): ?int
    {
        return $this->max_class_size ?? $this->course?->max_class_size;
    }

    public function rescheduleDeadlineDays(): int
    {
        return $this->school_reschedule_deadline_days
            ?? $this->course?->school_reschedule_deadline_days
            ?? CompanySetting::current()->school_reschedule_deadline_days;
    }

    /**
     * Until when a school may cancel or move its booking on its own.
     */
    public function rescheduleDeadline(): ?Carbon
    {
        return $this->firstDate?->starts_at->copy()->subDays($this->rescheduleDeadlineDays());
    }

    public function canStillBeRescheduled(): bool
    {
        return $this->rescheduleDeadline()?->isFuture() ?? false;
    }

    /**
     * @return list<SchoolVenue>
     */
    public function allowedVenues(): array
    {
        return $this->course?->effectiveSchoolFormat()->venues() ?? [SchoolVenue::ZLab];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function internalNotes(): HasMany
    {
        return $this->hasMany(CourseInstanceNote::class)->latest('id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(CourseInstanceNotification::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
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
     * Seats occupied by confirmed enrollments and by holds and pending confirmations that have not expired.
     */
    public function takenSeats(): int
    {
        return $this->enrollments()->occupyingSeat()->count();
    }

    /**
     * Seats only held temporarily (running bookings, pending confirmations, waitlist offers).
     */
    public function temporarilyHeldSeats(): int
    {
        return $this->enrollments()
            ->whereIn('status', EnrollmentStatus::temporarilyOccupying())
            ->where('expires_at', '>', now())
            ->count();
    }

    public function freeSeats(): int
    {
        return max(0, $this->capacity - $this->takenSeats());
    }

    public function waitlistCount(): int
    {
        return $this->enrollments()->onWaitlist()->count();
    }

    public function hasWaitlistRoomFor(int $seats): bool
    {
        return $this->waitlist_enabled
            && ($this->waitlist_limit === null || $this->waitlistCount() + $seats <= $this->waitlist_limit);
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
            return match (true) {
                $this->hasWaitlistRoomFor(1) => CourseAvailability::Waitlist,
                $this->temporarilyHeldSeats() > 0 => CourseAvailability::Reserved,
                default => CourseAvailability::Full,
            };
        }

        if ($freeSeats <= max(1, (int) ceil($this->capacity * 0.2))) {
            return CourseAvailability::FewSeatsLeft;
        }

        return CourseAvailability::Available;
    }
}
