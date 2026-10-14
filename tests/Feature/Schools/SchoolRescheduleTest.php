<?php

namespace Tests\Feature\Schools;

use App\Enums\RegistrationStatus;
use App\Livewire\Schools\SchoolCheckout;
use App\Mail\SchoolBookingUpdateMail;
use App\Mail\SchoolRequestMail;
use App\Models\Cart;
use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\Registration;
use App\Models\User;
use App\Services\Registrations\CartManager;
use App\Services\Schools\SchoolBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SchoolRescheduleTest extends TestCase
{
    use RefreshDatabase;

    protected Contact $teacher;

    protected Course $course;

    protected SchoolBookingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();

        CompanySetting::current()->update(['school_reschedule_deadline_days' => 14]);

        $this->service = app(SchoolBookingService::class);
        $this->teacher = Contact::factory()->teacher()->create();
        $this->course = Course::factory()->forSchoolClasses()->create(['responsible_user_id' => User::factory()->create(['email' => 'lead@example.com'])->id]);
    }

    protected function slot(int $daysAhead): CourseInstance
    {
        return CourseInstance::factory()->for($this->course)->withDates(1, now()->addDays($daysAhead)->setTime(9, 0))->create();
    }

    protected function confirmedBooking(CourseInstance $instance): Registration
    {
        $cart = Cart::factory()->create();
        $registration = $this->service->hold($instance, $cart, $this->teacher);
        $this->service->checkout($cart, [$registration->id => ['class_name' => '4b', 'students' => 24, 'teacher_name' => 'Frau Berg', 'topic' => 'Sensoren']], $this->teacher);
        $this->service->approve($registration->fresh());

        return $registration->fresh();
    }

    /**
     * Moves the booking to the new slot through the homepage and returns the new registration.
     */
    protected function requestMove(Registration $registration, CourseInstance $newSlot): Registration
    {
        $plainToken = Str::random(64);
        Cart::query()->create(['token_hash' => Cart::hashToken($plainToken), 'contact_id' => $this->teacher->id]);

        $this->actingAs($this->teacher, 'contact')
            ->withCookie(CartManager::COOKIE, $plainToken)
            ->post(route('homepage.schools.reschedule.hold', [$registration, $newSlot]))
            ->assertRedirect(route('homepage.schools.cart'));

        $move = Registration::query()->where('replaces_registration_id', $registration->id)->firstOrFail();

        Livewire::actingAs($this->teacher, 'contact')
            ->withCookies([CartManager::COOKIE => $plainToken])
            ->test(SchoolCheckout::class)
            ->assertSet("classes.{$move->id}.class_name", '4b')
            ->assertSet("classes.{$move->id}.topic", 'Sensoren')
            ->set('acceptsPrivacy', true)
            ->call('checkout')
            ->assertHasNoErrors();

        return $move->fresh();
    }

    public function test_teacher_cancels_before_the_deadline_and_the_course_lead_is_informed(): void
    {
        $instance = $this->slot(30);
        $registration = $this->confirmedBooking($instance);

        $this->actingAs($this->teacher, 'contact')
            ->post(route('homepage.schools.cancel', $registration))
            ->assertRedirect(route('homepage.schools.account'))
            ->assertSessionHas('status');

        $this->assertSame(RegistrationStatus::Cancelled, $registration->fresh()->status);
        $this->assertSame(1, $instance->fresh()->freeSeats());
        Mail::assertSent(SchoolBookingUpdateMail::class, fn (SchoolBookingUpdateMail $mail): bool => $mail->hasTo('lead@example.com') && $mail->kind === SchoolBookingUpdateMail::CANCELLED);
    }

    public function test_no_online_changes_after_the_deadline(): void
    {
        $registration = $this->confirmedBooking($this->slot(10));

        $this->actingAs($this->teacher, 'contact')
            ->post(route('homepage.schools.cancel', $registration))
            ->assertSessionHas('school_error');
        $this->get(route('homepage.schools.reschedule', $registration))->assertSessionHas('school_error');

        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
        Mail::assertNotSent(SchoolBookingUpdateMail::class);
    }

    public function test_other_teachers_cannot_change_the_booking(): void
    {
        $registration = $this->confirmedBooking($this->slot(30));

        $this->actingAs(Contact::factory()->teacher()->create(), 'contact')
            ->post(route('homepage.schools.cancel', $registration))
            ->assertNotFound();

        $this->actingAs($this->teacher->school, 'contact')
            ->post(route('homepage.schools.cancel', $registration))
            ->assertRedirect(route('homepage.schools.account'));

        $this->assertSame(RegistrationStatus::Cancelled, $registration->fresh()->status);
    }

    public function test_moved_booking_replaces_the_old_one_only_when_approved(): void
    {
        $oldSlot = $this->slot(30);
        $newSlot = $this->slot(40);
        $registration = $this->confirmedBooking($oldSlot);

        $this->actingAs($this->teacher, 'contact')
            ->get(route('homepage.schools.reschedule', $registration))
            ->assertOk()
            ->assertSee(route('homepage.schools.reschedule.hold', [$registration, $newSlot]), false);

        $move = $this->requestMove($registration, $newSlot);

        $this->assertSame(RegistrationStatus::Requested, $move->status);
        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
        $this->assertSame(0, $oldSlot->fresh()->freeSeats());
        Mail::assertSent(SchoolRequestMail::class, fn (SchoolRequestMail $mail): bool => $mail->registration->is($move) && str_starts_with($mail->envelope()->subject, 'Verschiebung'));

        $this->service->approve($move);

        $this->assertSame(RegistrationStatus::Confirmed, $move->fresh()->status);
        $this->assertSame(RegistrationStatus::Cancelled, $registration->fresh()->status);
        $this->assertSame(1, $oldSlot->fresh()->freeSeats());
    }

    public function test_declined_move_keeps_the_old_date(): void
    {
        $oldSlot = $this->slot(30);
        $registration = $this->confirmedBooking($oldSlot);
        $move = $this->requestMove($registration, $this->slot(40));

        $this->service->decline($move, 'Da ist schon eine andere Klasse.');

        $this->assertSame(RegistrationStatus::Declined, $move->fresh()->status);
        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
        $this->assertSame(0, $oldSlot->fresh()->freeSeats());
    }

    public function test_cancelling_the_original_also_drops_a_pending_move(): void
    {
        $registration = $this->confirmedBooking($this->slot(30));
        $newSlot = $this->slot(40);
        $move = $this->requestMove($registration, $newSlot);

        $this->service->cancelBySchool($registration);

        $this->assertSame(RegistrationStatus::Cancelled, $move->fresh()->status);
        $this->assertSame(1, $newSlot->fresh()->freeSeats());
    }

    public function test_teacher_cancels_via_the_signed_link_from_the_mail(): void
    {
        $registration = $this->confirmedBooking($this->slot(30));

        $this->get($this->service->visitUrl($registration))->assertOk()->assertSee('Stornieren');

        $this->post(URL::temporarySignedRoute('homepage.schools.visit.cancel', now()->addHour(), ['registration' => $registration->id]))
            ->assertRedirect();

        $this->assertSame(RegistrationStatus::Cancelled, $registration->fresh()->status);
        Mail::assertSent(SchoolBookingUpdateMail::class);
    }
}
