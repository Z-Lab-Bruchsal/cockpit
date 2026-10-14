<?php

namespace App\Services\Registrations;

use App\Enums\ContactType;
use App\Enums\CourseAvailability;
use App\Enums\EnrollmentStatus;
use App\Enums\RegistrationSource;
use App\Enums\RegistrationStatus;
use App\Exceptions\ReservationException;
use App\Mail\ApprovalRequestMail;
use App\Mail\RegistrationApprovalDeclinedMail;
use App\Mail\RegistrationAwaitingApprovalMail;
use App\Mail\RegistrationCancelledMail;
use App\Mail\RegistrationConfirmationMail;
use App\Mail\RegistrationConfirmedMail;
use App\Mail\WaitlistOfferMail;
use App\Models\Cart;
use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\CourseInstance;
use App\Models\Enrollment;
use App\Models\Kid;
use App\Models\Registration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * All state changes of course registrations: holding seats in a cart, checkout with double opt-in,
 * confirmation, approvals by the course lead, cancellation, expiry and moving up from the waitlist.
 *
 * Seat counting relies on Enrollment::scopeOccupyingSeat(), which ignores expired holds,
 * so availability stays correct even if the expiry job has not run yet.
 */
class CourseReservationService
{
    public const MAX_SEATS_PER_REGISTRATION = 5;

    /**
     * Holds seats in the cart. Siblings are never split up: if some but not enough seats are free,
     * all seats are held and the registration needs the course lead's approval after confirmation.
     * Without any free seat the family goes on the waitlist together.
     */
    public function hold(CourseInstance $instance, int $seats, Cart $cart): Registration
    {
        if ($seats < 1 || $seats > self::MAX_SEATS_PER_REGISTRATION) {
            throw new ReservationException('Bitte wähle zwischen 1 und '.self::MAX_SEATS_PER_REGISTRATION.' Plätzen.');
        }

        return DB::transaction(function () use ($instance, $seats, $cart): Registration {
            $instance = CourseInstance::query()->lockForUpdate()->findOrFail($instance->id);

            if (in_array($instance->availability(), [CourseAvailability::NotYetOpen, CourseAvailability::Closed, CourseAvailability::Cancelled], true)) {
                throw new ReservationException('Für diesen Kurs ist gerade keine Anmeldung möglich.');
            }

            if ($cart->heldRegistrations()->where('course_instance_id', $instance->id)->exists()) {
                throw new ReservationException('Dieser Kurs liegt schon in deinem Warenkorb.');
            }

            $expiresAt = now()->addMinutes(CompanySetting::current()->hold_minutes);

            $freeSeats = $instance->freeSeats();
            $needsApproval = false;

            if ($freeSeats >= $seats) {
                $status = EnrollmentStatus::Held;
            } elseif ($freeSeats > 0) {
                $status = EnrollmentStatus::Held;
                $needsApproval = true;
            } elseif ($instance->hasWaitlistRoomFor($seats)) {
                $status = EnrollmentStatus::Waitlisted;
            } else {
                throw new ReservationException('Dieser Kurs ist leider ausgebucht.');
            }

            $registration = $instance->registrations()->create([
                'cart_id' => $cart->id,
                'contact_id' => $cart->contact_id,
                'status' => RegistrationStatus::Held,
                'source' => RegistrationSource::Homepage,
                'expires_at' => $expiresAt,
                'needs_approval' => $needsApproval,
            ]);

            $waitlistPosition = $status === EnrollmentStatus::Waitlisted ? $this->nextWaitlistPosition($instance) : null;

            for ($seat = 0; $seat < $seats; $seat++) {
                $registration->enrollments()->create([
                    'course_instance_id' => $instance->id,
                    'status' => $status,
                    'expires_at' => $expiresAt,
                    'waitlist_position' => $waitlistPosition,
                ]);
            }

            return $registration->load('enrollments');
        });
    }

    /**
     * Removes a registration from the cart before checkout.
     */
    public function removeFromCart(Registration $registration): void
    {
        if ($registration->status !== RegistrationStatus::Held) {
            return;
        }

        $this->cancelRegistration($registration, notify: false);
    }

    /**
     * Completes the checkout of all held registrations in the cart.
     *
     * @param  array{email: string, name: string, phone?: ?string, wants_interest_mails?: bool, message?: ?string}  $contactData
     * @param  array<int, list<array{kid_id?: ?int, name?: ?string, birthdate?: ?string}>>  $kidsByRegistration  keyed by registration id, one entry per enrollment
     * @return Collection<int, Registration>
     */
    public function checkout(Cart $cart, array $contactData, array $kidsByRegistration, ?Contact $loggedInContact = null): Collection
    {
        $email = Str::lower(trim($contactData['email']));
        $plainToken = Str::random(64);
        $confirmationHours = CompanySetting::current()->confirmation_hours;

        $registrations = DB::transaction(function () use ($cart, $contactData, $kidsByRegistration, $loggedInContact, $email, $plainToken, $confirmationHours): Collection {
            $registrations = $cart->heldRegistrations()->with('enrollments')->lockForUpdate()->get();

            if ($registrations->isEmpty()) {
                throw new ReservationException('Deine Reservierung ist abgelaufen. Bitte lege die Kurse erneut in den Warenkorb.');
            }

            foreach ($registrations as $registration) {
                $kids = array_values($kidsByRegistration[$registration->id] ?? []);
                $activeEnrollments = $registration->enrollments->filter(fn (Enrollment $enrollment): bool => in_array($enrollment->status, [EnrollmentStatus::Held, EnrollmentStatus::Waitlisted], true))->values();

                if (count($kids) !== $activeEnrollments->count()) {
                    throw new ReservationException('Bitte gib für jeden Platz ein Kind an.');
                }

                $registration->update([
                    'contact_id' => $loggedInContact?->id,
                    'email' => $email,
                    'contact_name' => trim($contactData['name']),
                    'phone' => $contactData['phone'] ?? null,
                    'wants_interest_mails' => (bool) ($contactData['wants_interest_mails'] ?? false),
                    'message' => $contactData['message'] ?? null,
                    'status' => RegistrationStatus::PendingConfirmation,
                    'confirmation_token_hash' => hash('sha256', $plainToken),
                    'checked_out_at' => now(),
                    'expires_at' => now()->addHours($confirmationHours),
                ]);

                foreach ($activeEnrollments as $index => $enrollment) {
                    $kid = $kids[$index];
                    $ownKid = isset($kid['kid_id']) && $loggedInContact !== null
                        ? $loggedInContact->kids()->find($kid['kid_id'])
                        : null;

                    $enrollment->update([
                        'kid_id' => $ownKid?->id,
                        'kid_name' => $ownKid?->name ?? trim((string) ($kid['name'] ?? '')),
                        'kid_birthdate' => $ownKid?->exactBirthdate() ?? ($kid['birthdate'] ?? null),
                        'status' => $enrollment->status === EnrollmentStatus::Held ? EnrollmentStatus::Pending : EnrollmentStatus::Waitlisted,
                        'expires_at' => now()->addHours($confirmationHours),
                    ]);
                }
            }

            return $registrations;
        });

        // Logged-in parents have proven their address already, so no extra confirmation mail is needed.
        if ($loggedInContact !== null && $loggedInContact->hasVerifiedEmail() && $loggedInContact->email === $email) {
            $this->confirm($plainToken);

            return $registrations->map->fresh();
        }

        Mail::to($email)->send(new RegistrationConfirmationMail(
            registrations: $registrations->map->fresh(['courseInstance.course', 'courseInstance.dates', 'enrollments']),
            confirmationUrl: route('homepage.registration.show', $plainToken),
            name: trim($contactData['name']),
            confirmationHours: $confirmationHours,
        ));

        return $registrations->map->fresh();
    }

    /**
     * Confirms everything the token belongs to: the double opt-in after checkout, or a seat offered from the waitlist.
     *
     * @return Collection<int, Registration> confirmed registrations, empty if the token is invalid or expired
     */
    public function confirm(string $plainToken): Collection
    {
        $registrations = $this->findByToken($plainToken);

        if ($registrations->isEmpty()) {
            return $registrations;
        }

        $confirmed = DB::transaction(fn (): Collection => $registrations
            ->map(fn (Registration $registration): ?Registration => $this->confirmRegistration($registration))
            ->filter()
            ->values());

        $this->notifyAfterConfirmation($confirmed);

        return $confirmed;
    }

    /**
     * Registrations waiting for the given token (double opt-in or waitlist offer), without changing them.
     *
     * @return Collection<int, Registration>
     */
    public function findByToken(string $plainToken): Collection
    {
        return Registration::query()
            ->where('confirmation_token_hash', hash('sha256', $plainToken))
            ->with(['enrollments', 'courseInstance.course', 'courseInstance.dates'])
            ->get()
            ->filter(fn (Registration $registration): bool => $this->isAwaitingConfirmation($registration))
            ->values();
    }

    /**
     * Confirms a registration without token, e.g. by staff in the cockpit after a phone call.
     */
    public function confirmManually(Registration $registration): void
    {
        $confirmed = DB::transaction(fn (): ?Registration => $this->confirmRegistration($registration->fresh('enrollments'), ignoreExpiry: true));

        if ($confirmed !== null) {
            $this->notifyAfterConfirmation(collect([$confirmed]));
        }
    }

    public function findByManageToken(string $plainToken): ?Registration
    {
        return Registration::query()
            ->where('manage_token_hash', hash('sha256', $plainToken))
            ->with(['enrollments', 'courseInstance.course', 'courseInstance.dates'])
            ->first();
    }

    /**
     * Cancels the whole registration or a single child's seat and offers freed seats to the waitlist.
     */
    public function cancel(Registration $registration, ?Enrollment $enrollment = null, bool $notify = true): void
    {
        if ($enrollment === null) {
            $this->cancelRegistration($registration, $notify);

            return;
        }

        DB::transaction(function () use ($registration, $enrollment): void {
            $enrollment->update(['status' => EnrollmentStatus::Cancelled, 'cancelled_at' => now(), 'expires_at' => null]);

            if (! $registration->activeEnrollments()->exists()) {
                $registration->update(['status' => RegistrationStatus::Cancelled, 'cancelled_at' => now()]);
            }
        });

        if ($notify && $registration->email !== null) {
            Mail::to($registration->email)->send(new RegistrationCancelledMail($registration->fresh(['courseInstance.course', 'enrollments']), $enrollment));
        }

        $this->promoteWaitlist($registration->courseInstance);
    }

    /**
     * The course lead lets all siblings take part, even if the course is overbooked by it.
     */
    public function approveSiblings(Registration $registration): void
    {
        if (! $registration->awaitsSiblingApproval()) {
            return;
        }

        DB::transaction(function () use ($registration): void {
            $registration->enrollments()
                ->where('status', EnrollmentStatus::AwaitingApproval)
                ->update(['status' => EnrollmentStatus::Confirmed, 'expires_at' => null]);

            $registration->update([
                'status' => RegistrationStatus::Confirmed,
                'approval_decided_at' => now(),
            ]);
        });

        $this->sendConfirmedMail(collect([$registration->fresh()]), siblingsApproved: true);
    }

    /**
     * The course lead does not admit all siblings: the family goes on the waitlist together,
     * or is declined if the instance has no waitlist.
     */
    public function declineSiblings(Registration $registration): void
    {
        if (! $registration->awaitsSiblingApproval()) {
            return;
        }

        $instance = $registration->courseInstance;
        $toWaitlist = $instance->waitlist_enabled;

        DB::transaction(function () use ($registration, $instance, $toWaitlist): void {
            $position = $toWaitlist ? $this->nextWaitlistPosition($instance) : null;

            $registration->enrollments()
                ->where('status', EnrollmentStatus::AwaitingApproval)
                ->update($toWaitlist
                    ? ['status' => EnrollmentStatus::Waitlisted, 'expires_at' => null, 'waitlist_position' => $position]
                    : ['status' => EnrollmentStatus::Cancelled, 'expires_at' => null, 'cancelled_at' => now()]);

            $registration->update([
                'status' => $toWaitlist ? RegistrationStatus::Confirmed : RegistrationStatus::Declined,
                'approval_decided_at' => now(),
            ]);
        });

        if ($registration->email !== null) {
            Mail::to($registration->email)->send(new RegistrationApprovalDeclinedMail(
                registration: $registration->fresh(['courseInstance.course', 'courseInstance.dates', 'enrollments']),
                isOnWaitlist: $toWaitlist,
            ));
        }

        $this->promoteWaitlist($instance);
    }

    /**
     * The course lead lets a waitlist family move up although it is bigger than the free seats.
     */
    public function approveWaitlistMoveUp(Registration $registration): void
    {
        if (! $registration->awaitsWaitlistDecision()) {
            return;
        }

        $plainToken = DB::transaction(function () use ($registration): string {
            $registration->update(['approval_decided_at' => now(), 'waitlist_skipped_at_free_seats' => null]);

            return $this->offerSeats($registration, $registration->courseInstance);
        });

        $this->sendOfferMail($registration, $plainToken);
    }

    /**
     * The course lead does not let the family move up now. It keeps its waitlist position,
     * and the free seats go to the next families in order.
     */
    public function declineWaitlistMoveUp(Registration $registration): void
    {
        if (! $registration->awaitsWaitlistDecision()) {
            return;
        }

        $registration->update([
            'approval_requested_at' => null,
            'approval_decided_at' => now(),
            'waitlist_skipped_at_free_seats' => $registration->courseInstance->freeSeats(),
        ]);

        $this->promoteWaitlist($registration->courseInstance, seatsWereFreed: false);
    }

    /**
     * Expires holds, unconfirmed checkouts and unanswered waitlist offers, then offers freed seats to the waitlist.
     *
     * @return int number of expired enrollments
     */
    public function expireOverdue(): int
    {
        $overdue = Enrollment::query()
            ->whereIn('status', [...EnrollmentStatus::temporarilyOccupying(), EnrollmentStatus::Waitlisted])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        if ($overdue->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($overdue): void {
            foreach ($overdue as $enrollment) {
                // An unanswered waitlist offer ends the waitlist place as well.
                $enrollment->update(['status' => EnrollmentStatus::Expired, 'expires_at' => null]);
            }

            Registration::query()
                ->whereIn('id', $overdue->pluck('registration_id')->unique())
                ->whereIn('status', [RegistrationStatus::Held, RegistrationStatus::PendingConfirmation, RegistrationStatus::Confirmed])
                ->whereDoesntHave('enrollments', fn ($query) => $query->whereNotIn('status', [EnrollmentStatus::Cancelled, EnrollmentStatus::Expired]))
                ->update(['status' => RegistrationStatus::Expired]);
        });

        CourseInstance::query()
            ->whereIn('id', $overdue->pluck('course_instance_id')->unique())
            ->get()
            ->each(fn (CourseInstance $instance) => $this->promoteWaitlist($instance));

        return $overdue->count();
    }

    /**
     * Offers free seats to the waitlist strictly in order. If the next family is bigger than the free seats,
     * the course lead decides (approve or decline) and nobody else moves up until then. A family declined
     * for the current seats is skipped until seats are freed again.
     *
     * @param  bool  $seatsWereFreed  false when called after a decline, so the declined family is not asked again right away
     * @return int number of offered seats
     */
    public function promoteWaitlist(CourseInstance $instance, bool $seatsWereFreed = true): int
    {
        $result = DB::transaction(function () use ($instance, $seatsWereFreed): array {
            $instance = CourseInstance::query()->lockForUpdate()->findOrFail($instance->id);
            $offers = [];
            $approvalRequest = null;
            $offeredSeats = 0;

            if ($seatsWereFreed) {
                $instance->registrations()->whereNotNull('waitlist_skipped_at_free_seats')->update(['waitlist_skipped_at_free_seats' => null]);
            }

            $freeSeats = $instance->freeSeats();

            if ($freeSeats === 0) {
                return [$offers, $approvalRequest, $offeredSeats];
            }

            $waiting = $instance->enrollments()
                ->where('status', EnrollmentStatus::Waitlisted)
                ->whereNull('expires_at')
                ->orderBy('waitlist_position')
                ->orderBy('id')
                ->get()
                ->groupBy('registration_id');

            foreach ($waiting as $registrationId => $enrollments) {
                $registration = Registration::query()->with('enrollments')->findOrFail($registrationId);

                if ($registration->awaitsWaitlistDecision()) {
                    break;
                }

                if ($registration->waitlist_skipped_at_free_seats !== null) {
                    continue;
                }

                if ($enrollments->count() > $freeSeats) {
                    $registration->update(['approval_requested_at' => now(), 'approval_decided_at' => null]);
                    $approvalRequest = $registration;

                    break;
                }

                $offers[] = [$registration, $this->offerSeats($registration, $instance)];
                $freeSeats -= $enrollments->count();
                $offeredSeats += $enrollments->count();

                if ($freeSeats === 0) {
                    break;
                }
            }

            return [$offers, $approvalRequest, $offeredSeats];
        });

        [$offers, $approvalRequest, $offeredSeats] = $result;

        foreach ($offers as [$registration, $plainToken]) {
            $this->sendOfferMail($registration, $plainToken);
        }

        if ($approvalRequest !== null) {
            $this->requestApproval($approvalRequest);
        }

        return $offeredSeats;
    }

    /**
     * Registers a child directly from the cockpit, e.g. after a phone call.
     */
    public function registerManually(CourseInstance $instance, Kid $kid, ?Contact $contact = null, bool $ignoreCapacity = false): Registration
    {
        return DB::transaction(function () use ($instance, $kid, $contact, $ignoreCapacity): Registration {
            $instance = CourseInstance::query()->lockForUpdate()->findOrFail($instance->id);
            $contact ??= $kid->contacts()->first();

            if ($instance->enrollments()->where('kid_id', $kid->id)->whereNotIn('status', [EnrollmentStatus::Cancelled, EnrollmentStatus::Expired])->exists()) {
                throw new ReservationException("{$kid->name} ist für diese Durchführung bereits angemeldet.");
            }

            $hasSeat = $ignoreCapacity || $instance->freeSeats() > 0;

            if (! $hasSeat && ! $instance->waitlist_enabled) {
                throw new ReservationException('Die Durchführung ist ausgebucht und hat keine Warteliste.');
            }

            $registration = $instance->registrations()->create([
                'contact_id' => $contact?->id,
                'email' => $contact?->email,
                'contact_name' => $contact?->name,
                'phone' => $contact?->phone,
                'status' => RegistrationStatus::Confirmed,
                'source' => RegistrationSource::Cockpit,
                'confirmed_at' => now(),
            ]);

            $registration->enrollments()->create([
                'course_instance_id' => $instance->id,
                'kid_id' => $kid->id,
                'kid_name' => $kid->name,
                'status' => $hasSeat ? EnrollmentStatus::Confirmed : EnrollmentStatus::Waitlisted,
                'waitlist_position' => $hasSeat ? null : $this->nextWaitlistPosition($instance),
            ]);

            return $registration;
        });
    }

    protected function cancelRegistration(Registration $registration, bool $notify): void
    {
        $wasHeld = $registration->status === RegistrationStatus::Held;

        DB::transaction(function () use ($registration): void {
            $registration->enrollments()
                ->whereNotIn('status', [EnrollmentStatus::Cancelled, EnrollmentStatus::Expired])
                ->update(['status' => EnrollmentStatus::Cancelled, 'cancelled_at' => now(), 'expires_at' => null]);

            $registration->update(['status' => RegistrationStatus::Cancelled, 'cancelled_at' => now()]);
        });

        if ($notify && ! $wasHeld && $registration->email !== null) {
            Mail::to($registration->email)->send(new RegistrationCancelledMail($registration->fresh(['courseInstance.course', 'enrollments'])));
        }

        $this->promoteWaitlist($registration->courseInstance);
    }

    protected function isAwaitingConfirmation(Registration $registration): bool
    {
        $isOpenDoubleOptIn = $registration->status === RegistrationStatus::PendingConfirmation
            && $registration->expires_at?->isFuture();

        $hasOpenOffer = $registration->status === RegistrationStatus::Confirmed
            && $registration->enrollments->contains(fn (Enrollment $enrollment): bool => $enrollment->status === EnrollmentStatus::Offered && $enrollment->expires_at?->isFuture());

        return $isOpenDoubleOptIn || $hasOpenOffer;
    }

    /**
     * Turns pending seats into confirmed ones, confirms waitlist places and accepted offers,
     * and creates or links the contact and children now that the email address is proven.
     */
    protected function confirmRegistration(Registration $registration, bool $ignoreExpiry = false): ?Registration
    {
        if (! $ignoreExpiry && ! $this->isAwaitingConfirmation($registration)) {
            return null;
        }

        $contact = $registration->contact ?? $this->resolveContact($registration);

        $needsApproval = $registration->needs_approval && $registration->status === RegistrationStatus::PendingConfirmation;

        foreach ($registration->enrollments as $enrollment) {
            $next = match ($enrollment->status) {
                EnrollmentStatus::Pending => $needsApproval ? EnrollmentStatus::AwaitingApproval : EnrollmentStatus::Confirmed,
                EnrollmentStatus::Offered => EnrollmentStatus::Confirmed,
                EnrollmentStatus::Waitlisted => EnrollmentStatus::Waitlisted,
                default => null,
            };

            if ($next === null) {
                continue;
            }

            $enrollment->update([
                'status' => $next,
                'expires_at' => null,
                'kid_id' => $enrollment->kid_id ?? ($contact !== null ? $this->resolveKid($contact, $enrollment)->id : null),
            ]);
        }

        $registration->update([
            'contact_id' => $contact?->id,
            'status' => $needsApproval ? RegistrationStatus::AwaitingApproval : RegistrationStatus::Confirmed,
            'confirmed_at' => $registration->confirmed_at ?? now(),
            'confirmation_token_hash' => null,
            'expires_at' => null,
            'approval_requested_at' => $needsApproval ? now() : $registration->approval_requested_at,
        ]);

        return $registration;
    }

    protected function resolveContact(Registration $registration): ?Contact
    {
        if ($registration->email === null) {
            return null;
        }

        $contact = Contact::findByEmail($registration->email) ?? Contact::query()->create([
            'type' => ContactType::Parent,
            'email' => $registration->email,
            'name' => $registration->contact_name ?: Str::before($registration->email, '@'),
        ]);

        $contact->forceFill([
            'phone' => $contact->phone ?: $registration->phone,
            'email_verified_at' => $contact->email_verified_at ?? now(),
            'interest_mails_consent_at' => $contact->interest_mails_consent_at ?? ($registration->wants_interest_mails ? now() : null),
        ])->save();

        return $contact;
    }

    /**
     * Finds the child among the contact's children by name (and birthdate, if both are known) or creates it.
     */
    protected function resolveKid(Contact $contact, Enrollment $enrollment): Kid
    {
        $name = trim((string) $enrollment->kid_name);

        $kid = $contact->kids()
            ->whereRaw('lower(name) = ?', [Str::lower($name)])
            ->get()
            ->first(fn (Kid $kid): bool => $enrollment->kid_birthdate === null
                || $kid->exactBirthdate() === null
                || $kid->exactBirthdate()->isSameDay($enrollment->kid_birthdate));

        if ($kid === null) {
            $kid = Kid::query()->create(['name' => $name, 'birthdate' => $enrollment->kid_birthdate]);
            $contact->kids()->attach($kid);
        } elseif ($kid->exactBirthdate() === null && $enrollment->kid_birthdate !== null) {
            $kid->update(['birthdate' => $enrollment->kid_birthdate, 'birthdate_estimated' => false]);
        }

        return $kid;
    }

    /**
     * Confirmation mail for confirmed registrations; sibling registrations beyond the free seats
     * get an "awaiting approval" mail instead and the course lead is asked.
     *
     * @param  Collection<int, Registration>  $registrations
     */
    protected function notifyAfterConfirmation(Collection $registrations): void
    {
        [$awaitingApproval, $confirmed] = $registrations->partition(fn (Registration $registration): bool => $registration->awaitsSiblingApproval());

        if ($confirmed->isNotEmpty()) {
            $this->sendConfirmedMail($confirmed->values());
        }

        foreach ($awaitingApproval as $registration) {
            if ($registration->email !== null) {
                Mail::to($registration->email)->send(new RegistrationAwaitingApprovalMail(
                    $registration->fresh(['courseInstance.course', 'courseInstance.dates', 'enrollments']),
                ));
            }

            $this->requestApproval($registration);
        }
    }

    /**
     * Asks the course lead to approve or decline, via signed links in the mail.
     */
    protected function requestApproval(Registration $registration): void
    {
        $registration = $registration->fresh(['courseInstance.course', 'courseInstance.dates', 'courseInstance.responsibleUser', 'courseInstance.course.responsibleUser', 'enrollments.kid']);
        $recipient = $registration->courseInstance->approvalRecipient();

        if ($recipient === null) {
            return;
        }

        $url = fn (string $decision): string => URL::temporarySignedRoute(
            'approvals.show',
            now()->addDays(30),
            ['registration' => $registration->id, 'entscheidung' => $decision],
        );

        Mail::to($recipient)->send(new ApprovalRequestMail(
            registration: $registration,
            approveUrl: $url('freigeben'),
            declineUrl: $url('ablehnen'),
        ));
    }

    /**
     * Turns the family's waitlist places into offered seats and returns the plain token for the offer mail.
     */
    protected function offerSeats(Registration $registration, CourseInstance $instance): string
    {
        $plainToken = Str::random(64);
        $registration->update(['confirmation_token_hash' => hash('sha256', $plainToken), 'waitlist_skipped_at_free_seats' => null]);

        $registration->enrollments()
            ->where('status', EnrollmentStatus::Waitlisted)
            ->update(['status' => EnrollmentStatus::Offered, 'expires_at' => now()->addHours($instance->waitlistOfferHours())]);

        return $plainToken;
    }

    protected function sendOfferMail(Registration $registration, string $plainToken): void
    {
        if ($registration->email === null) {
            return;
        }

        $registration = $registration->fresh(['courseInstance.course', 'courseInstance.dates', 'enrollments']);

        Mail::to($registration->email)->send(new WaitlistOfferMail(
            registration: $registration,
            confirmationUrl: route('homepage.registration.show', $plainToken),
            confirmationHours: $registration->courseInstance->waitlistOfferHours(),
        ));
    }

    /**
     * @param  Collection<int, Registration>  $registrations
     */
    protected function sendConfirmedMail(Collection $registrations, bool $siblingsApproved = false): void
    {
        $email = $registrations->first()->email;

        if ($email === null) {
            return;
        }

        $manageUrls = $registrations->mapWithKeys(function (Registration $registration): array {
            $plainToken = Str::random(64);
            $registration->update(['manage_token_hash' => hash('sha256', $plainToken)]);

            return [$registration->id => route('homepage.registration.manage', $plainToken)];
        });

        Mail::to($email)->send(new RegistrationConfirmedMail(
            registrations: $registrations->map->fresh(['courseInstance.course', 'courseInstance.dates', 'enrollments.kid']),
            manageUrls: $manageUrls->all(),
            siblingsApproved: $siblingsApproved,
        ));
    }

    protected function nextWaitlistPosition(CourseInstance $instance): int
    {
        return (int) $instance->enrollments()->max('waitlist_position') + 1;
    }
}
