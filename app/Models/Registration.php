<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\RegistrationSource;
use App\Enums\RegistrationStatus;
use App\Enums\SchoolVenue;
use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Registration of one or more children for one course instance, made in one checkout.
 * For school class courses it is the booking of one class (one enrollment), see school_class_data.
 */
#[Fillable(['cart_id', 'contact_id', 'course_instance_id', 'status', 'source', 'email', 'contact_name', 'phone', 'wants_interest_mails', 'message', 'school_class_data', 'confirmation_token_hash', 'manage_token_hash', 'expires_at', 'checked_out_at', 'confirmed_at', 'cancelled_at', 'needs_approval', 'approval_requested_at', 'approval_decided_at', 'waitlist_skipped_at_free_seats', 'school_id', 'venue', 'replaces_registration_id', 'decision_note', 'teacher_reminded_at', 'teacher_confirmed_at', 'staff_remind_at', 'staff_reminded_at'])]
#[Hidden(['confirmation_token_hash', 'manage_token_hash'])]
class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'source' => RegistrationSource::class,
            'wants_interest_mails' => 'boolean',
            'school_class_data' => 'array',
            'expires_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'needs_approval' => 'boolean',
            'approval_requested_at' => 'datetime',
            'approval_decided_at' => 'datetime',
            'venue' => SchoolVenue::class,
            'teacher_reminded_at' => 'datetime',
            'teacher_confirmed_at' => 'datetime',
            'staff_remind_at' => 'datetime',
            'staff_reminded_at' => 'datetime',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function courseInstance(): BelongsTo
    {
        return $this->belongsTo(CourseInstance::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'school_id');
    }

    /**
     * The booking this one moves to another date; it stays valid until this one is approved.
     */
    public function replacesRegistration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'replaces_registration_id');
    }

    public function internalNotes(): HasMany
    {
        return $this->hasMany(CourseInstanceNote::class)->latest('id');
    }

    public function isSchoolBooking(): bool
    {
        return $this->school_id !== null;
    }

    /**
     * The booking teacher and the school account may manage a school booking.
     */
    public function canBeManagedBy(?Contact $contact): bool
    {
        return $contact !== null && $this->isSchoolBooking()
            && ($this->contact_id === $contact->id || ($contact->isSchool() && $this->school_id === $contact->id));
    }

    public function awaitsSchoolDecision(): bool
    {
        return $this->status === RegistrationStatus::Requested;
    }

    /**
     * Class details entered at checkout.
     *
     * @return array{class_name: string, students: int, teacher_name: string, teacher_email: ?string, teacher_phone: ?string, topic: ?string}
     */
    public function schoolClass(): array
    {
        $data = $this->school_class_data ?? [];

        return [
            'class_name' => (string) ($data['class_name'] ?? ''),
            'students' => (int) ($data['students'] ?? 0),
            'teacher_name' => (string) ($data['teacher_name'] ?? $this->contact_name ?? ''),
            'teacher_email' => $data['teacher_email'] ?? $this->email,
            'teacher_phone' => $data['teacher_phone'] ?? $this->phone,
            'topic' => $data['topic'] ?? null,
        ];
    }

    /**
     * The address where the visit takes place: the school's address or the Z-LAB location.
     */
    public function visitAddress(): string
    {
        if ($this->venue === SchoolVenue::School) {
            return $this->school?->addressLine() ?: (string) $this->school?->name;
        }

        $firstDate = $this->courseInstance?->dates->first();

        return (string) ($firstDate?->effectiveLocation() ?? $this->courseInstance?->location);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class)->orderBy('id');
    }

    public function activeEnrollments(): HasMany
    {
        return $this->enrollments()->whereNotIn('status', [EnrollmentStatus::Cancelled, EnrollmentStatus::Expired]);
    }

    public function isOnWaitlist(): bool
    {
        return $this->activeEnrollments->isNotEmpty()
            && $this->activeEnrollments->every(fn (Enrollment $enrollment): bool => $enrollment->status === EnrollmentStatus::Waitlisted);
    }

    /**
     * A family on the waitlist that does not fit into the freed seats and waits for the course lead's decision.
     */
    public function awaitsWaitlistDecision(): bool
    {
        return $this->approval_requested_at !== null && $this->approval_decided_at === null && $this->isOnWaitlist();
    }

    public function awaitsSiblingApproval(): bool
    {
        return $this->status === RegistrationStatus::AwaitingApproval;
    }

    public function hasOpenOffer(): bool
    {
        return $this->activeEnrollments->contains(fn (Enrollment $enrollment): bool => $enrollment->status === EnrollmentStatus::Offered);
    }

    public function displayContactName(): string
    {
        return $this->contact?->name ?? $this->contact_name ?? (string) $this->email;
    }
}
