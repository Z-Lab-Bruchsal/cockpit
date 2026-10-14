<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\RegistrationSource;
use App\Enums\RegistrationStatus;
use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Registration of one or more children for one course instance, made in one checkout.
 */
#[Fillable(['cart_id', 'contact_id', 'course_instance_id', 'status', 'source', 'email', 'contact_name', 'phone', 'wants_interest_mails', 'message', 'school_class_data', 'confirmation_token_hash', 'manage_token_hash', 'expires_at', 'checked_out_at', 'confirmed_at', 'cancelled_at', 'needs_approval', 'approval_requested_at', 'approval_decided_at', 'waitlist_skipped_at_free_seats'])]
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
