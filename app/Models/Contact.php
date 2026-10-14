<?php

namespace App\Models;

use App\Enums\ContactType;
use App\Mail\ContactEmailChangeMail;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Parent or school. A contact does not need an account: it is also created for guest bookings.
 * Logging in happens passwordless via magic link (guard "contact").
 */
#[Fillable(['type', 'name', 'email', 'phone', 'street', 'zip', 'city', 'contact_person', 'notes', 'email_verified_at', 'interest_mails_consent_at', 'last_login_at', 'digest_hours', 'max_course_mails_per_week'])]
#[Hidden(['remember_token', 'unsubscribe_token'])]
class Contact extends Authenticatable
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory, Notifiable;

    /**
     * Set when the contact itself confirmed the new address, so the next email change keeps it verified.
     */
    public bool $newEmailConfirmedByContact = false;

    protected static function booted(): void
    {
        static::creating(function (Contact $contact): void {
            $contact->unsubscribe_token ??= Str::random(48);
            $contact->email = $contact->email !== null ? Str::lower(trim($contact->email)) : null;
        });

        static::updating(function (Contact $contact): void {
            if (! $contact->isDirty('email')) {
                return;
            }

            if ($contact->email !== null) {
                $contact->email = Str::lower(trim($contact->email));
            }

            // A new address set in the cockpit is not confirmed by the contact yet.
            if ($contact->newEmailConfirmedByContact) {
                $contact->email_verified_at = now();
                $contact->newEmailConfirmedByContact = false;
            } else {
                $contact->email_verified_at = null;
            }
        });

        // Every change of the address, from the homepage or in the cockpit, is reported to the old address.
        static::updated(function (Contact $contact): void {
            $oldEmail = $contact->getOriginal('email');

            if (! $contact->wasChanged('email') || blank($oldEmail) || $oldEmail === $contact->email) {
                return;
            }

            ContactEmailChange::query()->where('contact_id', $contact->id)->pending()->update(['cancelled_at' => now()]);

            Mail::to($oldEmail)->send(new ContactEmailChangeMail(
                step: ContactEmailChangeMail::STEP_CHANGED,
                newEmail: (string) ($contact->email ?? '–'),
                name: $contact->name,
            ));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContactType::class,
            'email_verified_at' => 'datetime',
            'interest_mails_consent_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function kids(): BelongsToMany
    {
        return $this->belongsToMany(Kid::class)->orderBy('name');
    }

    public function courseInstanceNotifications(): HasMany
    {
        return $this->hasMany(CourseInstanceNotification::class);
    }

    public function courseDigests(): HasMany
    {
        return $this->hasMany(CourseDigest::class);
    }

    /**
     * Hours without new courses after which collected course infos are sent: own setting, else company default.
     */
    public function effectiveDigestHours(): int
    {
        return $this->digest_hours ?? CompanySetting::current()->digest_hours;
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function emailChanges(): HasMany
    {
        return $this->hasMany(ContactEmailChange::class);
    }

    public function pendingEmailChange(): ?ContactEmailChange
    {
        return $this->emailChanges()->pending()->latest('id')->first();
    }

    public function loginTokens(): HasMany
    {
        return $this->hasMany(ContactLoginToken::class);
    }

    /**
     * @param  Builder<Contact>  $query
     */
    public function scopeParents(Builder $query): void
    {
        $query->where('type', ContactType::Parent);
    }

    /**
     * @param  Builder<Contact>  $query
     */
    public function scopeSchools(Builder $query): void
    {
        $query->where('type', ContactType::School);
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function wantsInterestMails(): bool
    {
        return $this->interest_mails_consent_at !== null;
    }

    public static function findByEmail(string $email): ?self
    {
        return static::query()->where('email', Str::lower(trim($email)))->first();
    }
}
