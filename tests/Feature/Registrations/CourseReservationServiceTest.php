<?php

namespace Tests\Feature\Registrations;

use App\Enums\CourseAvailability;
use App\Enums\EnrollmentStatus;
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
use App\Models\Kid;
use App\Models\Registration;
use App\Models\User;
use App\Services\Registrations\CourseReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CourseReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CourseReservationService $reservations;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        CompanySetting::current()->update(['hold_minutes' => 15, 'confirmation_hours' => 48, 'waitlist_offer_hours' => 48]);
        $this->reservations = app(CourseReservationService::class);
    }

    protected function courseInstance(int $capacity = 2, bool $waitlist = true): CourseInstance
    {
        return CourseInstance::factory()->withDates(2)->create(['capacity' => $capacity, 'waitlist_enabled' => $waitlist]);
    }

    /**
     * Holds seats and checks out as a guest, returning the plain confirmation token from the mail.
     *
     * @param  list<string>  $kidNames
     */
    protected function bookAsGuest(CourseInstance $instance, array $kidNames, string $email = 'eltern@example.com'): string
    {
        $cart = Cart::factory()->create();
        $registration = $this->reservations->hold($instance, count($kidNames), $cart);

        $this->reservations->checkout($cart, ['email' => $email, 'name' => 'Familie Muster'], [
            $registration->id => array_map(fn (string $name): array => ['name' => $name, 'birthdate' => now()->subYears(10)->toDateString()], $kidNames),
        ]);

        return $this->lastTokenFrom(RegistrationConfirmationMail::class);
    }

    protected function lastTokenFrom(string $mailable): string
    {
        $mail = Mail::sent($mailable)->last();

        return basename(parse_url($mail->confirmationUrl, PHP_URL_PATH));
    }

    public function test_holding_seats_reduces_free_seats_for_everyone(): void
    {
        $instance = $this->courseInstance(capacity: 3);

        $registration = $this->reservations->hold($instance, 2, Cart::factory()->create());

        $this->assertSame(RegistrationStatus::Held, $registration->status);
        $this->assertCount(2, $registration->enrollments);
        $this->assertSame(1, $instance->freeSeats());
    }

    public function test_expired_holds_free_their_seats_even_before_the_job_runs(): void
    {
        $instance = $this->courseInstance(capacity: 1);
        $this->reservations->hold($instance, 1, Cart::factory()->create());

        $this->assertSame(0, $instance->freeSeats());

        $this->travel(16)->minutes();

        $this->assertSame(1, $instance->freeSeats());
    }

    public function test_seats_held_by_others_are_shown_as_reserved_when_no_waitlist(): void
    {
        $instance = $this->courseInstance(capacity: 1, waitlist: false);
        $this->reservations->hold($instance, 1, Cart::factory()->create());

        $this->assertSame(CourseAvailability::Reserved, $instance->availability());

        $this->expectException(ReservationException::class);
        $this->reservations->hold($instance, 1, Cart::factory()->create());
    }

    public function test_family_without_any_free_seat_goes_to_waitlist_together(): void
    {
        $instance = $this->courseInstance(capacity: 1);
        $this->reservations->hold($instance, 1, Cart::factory()->create());

        $registration = $this->reservations->hold($instance, 2, Cart::factory()->create());

        $this->assertTrue($registration->isOnWaitlist());
        $this->assertFalse($registration->needs_approval);
    }

    public function test_siblings_beyond_free_seats_wait_for_approval_after_confirmation(): void
    {
        $lead = User::factory()->create(['email' => 'lead@example.com']);
        $instance = $this->courseInstance(capacity: 3);
        $instance->update(['responsible_user_id' => $lead->id]);
        $this->reservations->confirm($this->bookAsGuest($instance, ['Max'], 'erste@example.com'));

        $token = $this->bookAsGuest($instance, ['Paul', 'Lena', 'Tom']);
        $registration = Registration::query()->where('email', 'eltern@example.com')->sole();
        $this->assertTrue($registration->needs_approval);

        $this->reservations->confirm($token);

        $registration->refresh();
        $this->assertSame(RegistrationStatus::AwaitingApproval, $registration->status);
        $this->assertTrue($registration->enrollments->every(fn ($enrollment): bool => $enrollment->status === EnrollmentStatus::AwaitingApproval));
        $this->assertSame(0, $instance->freeSeats(), 'Seats waiting for approval are blocked.');
        Mail::assertSent(RegistrationAwaitingApprovalMail::class, fn ($mail): bool => $mail->hasTo('eltern@example.com'));
        Mail::assertSent(ApprovalRequestMail::class, fn ($mail): bool => $mail->hasTo('lead@example.com'));
        Mail::assertNotSent(RegistrationConfirmedMail::class, fn ($mail): bool => $mail->hasTo('eltern@example.com'));
    }

    public function test_approved_siblings_all_take_part_even_if_overbooked(): void
    {
        $instance = $this->courseInstance(capacity: 2);
        $this->reservations->confirm($this->bookAsGuest($instance, ['Max'], 'erste@example.com'));
        $this->reservations->confirm($this->bookAsGuest($instance, ['Paul', 'Lena']));
        $registration = Registration::query()->where('email', 'eltern@example.com')->sole();

        $this->reservations->approveSiblings($registration);

        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
        $this->assertSame(3, $instance->takenSeats());
        Mail::assertSent(RegistrationConfirmedMail::class, fn ($mail): bool => $mail->hasTo('eltern@example.com') && $mail->siblingsApproved);
    }

    public function test_declined_siblings_go_on_the_waitlist_together(): void
    {
        $instance = $this->courseInstance(capacity: 2);
        $this->reservations->confirm($this->bookAsGuest($instance, ['Max'], 'erste@example.com'));
        $this->reservations->confirm($this->bookAsGuest($instance, ['Paul', 'Lena']));
        $registration = Registration::query()->where('email', 'eltern@example.com')->sole();

        $this->reservations->declineSiblings($registration);

        $registration->refresh();
        $this->assertTrue($registration->isOnWaitlist());
        $this->assertSame(1, $instance->freeSeats());
        Mail::assertSent(RegistrationApprovalDeclinedMail::class, fn ($mail): bool => $mail->hasTo('eltern@example.com') && $mail->isOnWaitlist);
    }

    public function test_declined_siblings_without_waitlist_are_declined(): void
    {
        $instance = $this->courseInstance(capacity: 2, waitlist: false);
        $this->reservations->confirm($this->bookAsGuest($instance, ['Max'], 'erste@example.com'));
        $this->reservations->confirm($this->bookAsGuest($instance, ['Paul', 'Lena']));
        $registration = Registration::query()->where('email', 'eltern@example.com')->sole();

        $this->reservations->declineSiblings($registration);

        $this->assertSame(RegistrationStatus::Declined, $registration->fresh()->status);
        Mail::assertSent(RegistrationApprovalDeclinedMail::class, fn ($mail): bool => ! $mail->isOnWaitlist);
    }

    public function test_full_course_without_waitlist_rejects_holds(): void
    {
        $instance = $this->courseInstance(capacity: 1, waitlist: false);
        $this->reservations->hold($instance, 1, Cart::factory()->create());
        $this->travel(1)->minutes();

        try {
            $this->reservations->hold($instance, 2, Cart::factory()->create());
            $this->fail('Expected a ReservationException.');
        } catch (ReservationException $exception) {
            $this->assertStringContainsString('ausgebucht', $exception->getMessage());
        }
    }

    public function test_waitlist_limit_is_respected(): void
    {
        $instance = CourseInstance::factory()->withDates()->create(['capacity' => 1, 'waitlist_enabled' => true, 'waitlist_limit' => 1]);
        $this->reservations->hold($instance, 1, Cart::factory()->create());
        $this->reservations->hold($instance, 1, Cart::factory()->create());

        $this->expectException(ReservationException::class);
        $this->reservations->hold($instance, 1, Cart::factory()->create());
    }

    public function test_same_course_cannot_be_added_twice_to_a_cart(): void
    {
        $instance = $this->courseInstance(capacity: 5);
        $cart = Cart::factory()->create();
        $this->reservations->hold($instance, 1, $cart);

        $this->expectException(ReservationException::class);
        $this->reservations->hold($instance, 1, $cart);
    }

    public function test_closed_or_draft_instances_cannot_be_booked(): void
    {
        $this->expectException(ReservationException::class);

        $this->reservations->hold(CourseInstance::factory()->draft()->withDates()->create(), 1, Cart::factory()->create());
    }

    public function test_guest_checkout_sends_double_opt_in_and_creates_nothing_before_confirmation(): void
    {
        $instance = $this->courseInstance(capacity: 3);

        $token = $this->bookAsGuest($instance, ['Paul', 'Lena']);

        Mail::assertSent(RegistrationConfirmationMail::class, fn (RegistrationConfirmationMail $mail): bool => $mail->hasTo('eltern@example.com'));
        $this->assertSame(0, Contact::query()->count());
        $this->assertSame(0, Kid::query()->count());

        $registration = Registration::query()->sole();
        $this->assertSame(RegistrationStatus::PendingConfirmation, $registration->status);
        $this->assertSame(2, $instance->takenSeats());

        $confirmed = $this->reservations->confirm($token);

        $this->assertCount(1, $confirmed);
        $registration->refresh();
        $this->assertSame(RegistrationStatus::Confirmed, $registration->status);
        $this->assertTrue($registration->enrollments->every(fn ($enrollment): bool => $enrollment->status === EnrollmentStatus::Confirmed));

        $contact = Contact::query()->sole();
        $this->assertSame('eltern@example.com', $contact->email);
        $this->assertTrue($contact->hasVerifiedEmail());
        $this->assertEqualsCanonicalizing(['Paul', 'Lena'], $contact->kids->pluck('name')->all());
        $this->assertSame(10, $contact->kids->first()->age());

        Mail::assertSent(RegistrationConfirmedMail::class);
    }

    public function test_confirmation_token_works_only_once_and_expires(): void
    {
        $token = $this->bookAsGuest($this->courseInstance(), ['Paul']);

        $this->assertCount(1, $this->reservations->confirm($token));
        $this->assertCount(0, $this->reservations->confirm($token));

        $otherToken = $this->bookAsGuest($this->courseInstance(), ['Lena']);
        $this->travel(49)->hours();

        $this->assertCount(0, $this->reservations->confirm($otherToken));
    }

    public function test_one_confirmation_covers_all_courses_of_a_checkout(): void
    {
        $first = $this->courseInstance();
        $second = $this->courseInstance();
        $cart = Cart::factory()->create();
        $a = $this->reservations->hold($first, 1, $cart);
        $b = $this->reservations->hold($second, 1, $cart);

        $this->reservations->checkout($cart, ['email' => 'eltern@example.com', 'name' => 'Muster'], [
            $a->id => [['name' => 'Paul', 'birthdate' => '2016-01-01']],
            $b->id => [['name' => 'Paul', 'birthdate' => '2016-01-01']],
        ]);

        Mail::assertSentCount(1);
        $this->assertCount(2, $this->reservations->confirm($this->lastTokenFrom(RegistrationConfirmationMail::class)));
        $this->assertSame(1, Kid::query()->count(), 'The same child must not be created twice.');
    }

    public function test_confirmation_reuses_existing_contact_and_child(): void
    {
        $contact = Contact::factory()->create(['email' => 'eltern@example.com']);
        $kid = Kid::factory()->create(['name' => 'Paul', 'birthdate' => now()->subYears(10)->toDateString()]);
        $contact->kids()->attach($kid);

        $this->reservations->confirm($this->bookAsGuest($this->courseInstance(), ['paul']));

        $this->assertSame(1, Contact::query()->count());
        $this->assertSame(1, Kid::query()->count());
        $this->assertSame($kid->id, Registration::query()->sole()->enrollments->first()->kid_id);
    }

    public function test_logged_in_verified_parent_is_confirmed_without_extra_mail(): void
    {
        $contact = Contact::factory()->create(['email' => 'eltern@example.com']);
        $kid = Kid::factory()->create();
        $contact->kids()->attach($kid);
        $cart = Cart::factory()->create(['contact_id' => $contact->id]);
        $registration = $this->reservations->hold($this->courseInstance(), 1, $cart);

        $this->reservations->checkout($cart, ['email' => 'eltern@example.com', 'name' => $contact->name], [
            $registration->id => [['kid_id' => $kid->id]],
        ], $contact);

        Mail::assertNotSent(RegistrationConfirmationMail::class);
        Mail::assertSent(RegistrationConfirmedMail::class);
        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
        $this->assertSame($kid->id, $registration->enrollments()->first()->kid_id);
    }

    public function test_checkout_after_hold_expired_fails(): void
    {
        $cart = Cart::factory()->create();
        $registration = $this->reservations->hold($this->courseInstance(), 1, $cart);
        $this->travel(16)->minutes();

        $this->expectException(ReservationException::class);
        $this->reservations->checkout($cart, ['email' => 'a@example.com', 'name' => 'A'], [$registration->id => [['name' => 'Paul', 'birthdate' => '2016-01-01']]]);
    }

    public function test_cancellation_offers_the_seat_to_the_waitlist(): void
    {
        $instance = $this->courseInstance(capacity: 1);
        $this->reservations->confirm($this->bookAsGuest($instance, ['Paul'], 'erste@example.com'));
        $this->reservations->confirm($this->bookAsGuest($instance, ['Lena'], 'zweite@example.com'));

        $waiting = Registration::query()->where('email', 'zweite@example.com')->sole();
        $this->assertTrue($waiting->isOnWaitlist());
        $this->assertSame(RegistrationStatus::Confirmed, $waiting->status);

        $first = Registration::query()->where('email', 'erste@example.com')->sole();
        $this->reservations->cancel($first);

        Mail::assertSent(RegistrationCancelledMail::class, fn ($mail): bool => $mail->hasTo('erste@example.com'));
        Mail::assertSent(WaitlistOfferMail::class, fn ($mail): bool => $mail->hasTo('zweite@example.com'));
        $this->assertSame(EnrollmentStatus::Offered, $waiting->enrollments()->first()->status);
        $this->assertSame(0, $instance->freeSeats(), 'The offered seat is reserved for the family on the waitlist.');

        $this->reservations->confirm($this->lastTokenFrom(WaitlistOfferMail::class));

        $this->assertSame(EnrollmentStatus::Confirmed, $waiting->enrollments()->first()->status);
    }

    public function test_unanswered_offer_expires_and_goes_to_the_next_family(): void
    {
        $instance = $this->courseInstance(capacity: 1);
        $this->reservations->confirm($this->bookAsGuest($instance, ['Paul'], 'erste@example.com'));
        $this->reservations->confirm($this->bookAsGuest($instance, ['Lena'], 'zweite@example.com'));
        $this->reservations->confirm($this->bookAsGuest($instance, ['Max'], 'dritte@example.com'));

        $this->reservations->cancel(Registration::query()->where('email', 'erste@example.com')->sole());
        $this->travel(49)->hours();
        $this->reservations->expireOverdue();

        $this->assertSame(RegistrationStatus::Expired, Registration::query()->where('email', 'zweite@example.com')->sole()->status);
        Mail::assertSent(WaitlistOfferMail::class, fn ($mail): bool => $mail->hasTo('dritte@example.com'));
    }

    public function test_waitlist_strictly_in_order_and_course_lead_decides_about_big_families(): void
    {
        $lead = User::factory()->create(['email' => 'lead@example.com']);
        $instance = $this->courseInstance(capacity: 2);
        $instance->course->update(['responsible_user_id' => $lead->id]);
        $this->reservations->confirm($this->bookAsGuest($instance, ['Paul'], 'eins@example.com'));
        $this->reservations->confirm($this->bookAsGuest($instance, ['Lena'], 'zwei@example.com'));
        $this->reservations->confirm($this->bookAsGuest($instance, ['Max', 'Moritz'], 'geschwister@example.com'));
        $this->reservations->confirm($this->bookAsGuest($instance, ['Mia'], 'einzeln@example.com'));
        $siblings = Registration::query()->where('email', 'geschwister@example.com')->sole();

        $this->reservations->cancel(Registration::query()->where('email', 'eins@example.com')->sole());

        Mail::assertNotSent(WaitlistOfferMail::class);
        Mail::assertSent(ApprovalRequestMail::class, fn ($mail): bool => $mail->hasTo('lead@example.com') && $mail->registration->is($siblings));
        $this->assertTrue($siblings->fresh()->awaitsWaitlistDecision());

        // Nobody else moves up while the decision is pending, also not via the expiry job.
        $this->reservations->expireOverdue();
        $this->reservations->promoteWaitlist($instance);
        Mail::assertNotSent(WaitlistOfferMail::class);

        $this->reservations->declineWaitlistMoveUp($siblings->fresh());

        Mail::assertSent(WaitlistOfferMail::class, fn ($mail): bool => $mail->hasTo('einzeln@example.com'));
        $siblings->refresh();
        $this->assertTrue($siblings->isOnWaitlist());
        $this->assertFalse($siblings->awaitsWaitlistDecision());
        $this->assertSame(1, $siblings->enrollments->first()->waitlist_position, 'The family keeps its position.');
    }

    public function test_declined_family_is_asked_again_when_more_seats_become_free(): void
    {
        $instance = $this->courseInstance(capacity: 2);
        $this->reservations->confirm($this->bookAsGuest($instance, ['Paul'], 'eins@example.com'));
        $this->reservations->confirm($this->bookAsGuest($instance, ['Lena'], 'zwei@example.com'));
        $this->reservations->confirm($this->bookAsGuest($instance, ['Max', 'Moritz'], 'geschwister@example.com'));
        $siblings = Registration::query()->where('email', 'geschwister@example.com')->sole();

        $this->reservations->cancel(Registration::query()->where('email', 'eins@example.com')->sole());
        $this->reservations->declineWaitlistMoveUp($siblings->fresh());
        Mail::assertSentCount(1 + 1 + 1 + 1 + 1 + 1 + 1 + 1);

        $this->reservations->cancel(Registration::query()->where('email', 'zwei@example.com')->sole());

        Mail::assertSent(WaitlistOfferMail::class, fn ($mail): bool => $mail->hasTo('geschwister@example.com'));
    }

    public function test_approved_big_family_gets_all_seats_offered(): void
    {
        $instance = $this->courseInstance(capacity: 2);
        $this->reservations->confirm($this->bookAsGuest($instance, ['Paul'], 'eins@example.com'));
        $this->reservations->confirm($this->bookAsGuest($instance, ['Lena'], 'zwei@example.com'));
        $this->reservations->confirm($this->bookAsGuest($instance, ['Max', 'Moritz'], 'geschwister@example.com'));
        $siblings = Registration::query()->where('email', 'geschwister@example.com')->sole();

        $this->reservations->cancel(Registration::query()->where('email', 'eins@example.com')->sole());
        $this->reservations->approveWaitlistMoveUp($siblings->fresh());

        Mail::assertSent(WaitlistOfferMail::class, fn ($mail): bool => $mail->hasTo('geschwister@example.com'));
        $this->assertSame(2, $siblings->enrollments()->where('status', EnrollmentStatus::Offered)->count());
    }

    public function test_offer_deadline_can_be_set_per_course_and_instance(): void
    {
        CompanySetting::current()->update(['waitlist_offer_hours' => 48]);
        $instance = $this->courseInstance(capacity: 1);
        $instance->course->update(['waitlist_offer_hours' => 24]);

        $this->assertSame(24, $instance->fresh()->waitlistOfferHours());

        $instance->update(['waitlist_offer_hours' => 6]);
        $instance->refresh();
        $this->assertSame(6, $instance->waitlistOfferHours());

        $this->reservations->confirm($this->bookAsGuest($instance, ['Paul'], 'eins@example.com'));
        $this->reservations->confirm($this->bookAsGuest($instance, ['Lena'], 'zwei@example.com'));

        Mail::assertSent(RegistrationConfirmedMail::class, fn ($mail): bool => $mail->hasTo('zwei@example.com') && str_contains($mail->render(), '6 Stunden'));

        $this->reservations->cancel(Registration::query()->where('email', 'eins@example.com')->sole());

        Mail::assertSent(WaitlistOfferMail::class, fn ($mail): bool => $mail->confirmationHours === 6);
        $this->travel(7)->hours();
        $this->reservations->expireOverdue();
        $this->assertSame(RegistrationStatus::Expired, Registration::query()->where('email', 'zwei@example.com')->sole()->status);
    }

    public function test_unconfirmed_checkout_expires_and_frees_seats(): void
    {
        $instance = $this->courseInstance(capacity: 1);
        $this->bookAsGuest($instance, ['Paul']);

        $this->travel(49)->hours();
        $this->assertSame(1, $this->reservations->expireOverdue());

        $this->assertSame(RegistrationStatus::Expired, Registration::query()->sole()->status);
        $this->assertSame(1, $instance->freeSeats());
    }

    public function test_cancelling_one_child_keeps_the_sibling(): void
    {
        $instance = $this->courseInstance(capacity: 3);
        $this->reservations->confirm($this->bookAsGuest($instance, ['Paul', 'Lena']));
        $registration = Registration::query()->sole();

        $this->reservations->cancel($registration, $registration->enrollments->first());

        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
        $this->assertSame(1, $instance->takenSeats());
    }

    public function test_cockpit_can_register_kid_and_overbook(): void
    {
        $instance = $this->courseInstance(capacity: 1, waitlist: false);
        $first = Kid::factory()->create();
        $second = Kid::factory()->create();

        $this->reservations->registerManually($instance, $first);
        $this->assertSame(0, $instance->freeSeats());

        try {
            $this->reservations->registerManually($instance, $second);
            $this->fail('Expected a ReservationException.');
        } catch (ReservationException) {
        }

        $this->reservations->registerManually($instance, $second, ignoreCapacity: true);
        $this->assertSame(2, $instance->takenSeats());

        $this->expectException(ReservationException::class);
        $this->reservations->registerManually($instance, $second, ignoreCapacity: true);
    }
}
