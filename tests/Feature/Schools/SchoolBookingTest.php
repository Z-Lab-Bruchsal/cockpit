<?php

namespace Tests\Feature\Schools;

use App\Enums\EnrollmentStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SchoolCourseFormat;
use App\Enums\SchoolSlotState;
use App\Enums\SchoolVenue;
use App\Exceptions\ReservationException;
use App\Filament\Resources\Registrations\Actions\ApproveSchoolRequestAction;
use App\Filament\Resources\Registrations\Actions\DeclineSchoolRequestAction;
use App\Filament\Resources\Registrations\Pages\ListRegistrations;
use App\Filament\Resources\Registrations\Pages\ViewRegistration;
use App\Livewire\Schools\SchoolCheckout;
use App\Mail\SchoolRequestDecidedMail;
use App\Mail\SchoolRequestMail;
use App\Mail\SchoolRequestReceivedMail;
use App\Models\Cart;
use App\Models\Contact;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\Registration;
use App\Models\User;
use App\Services\Registrations\CartManager;
use App\Services\Registrations\CourseReservationService;
use App\Services\Schools\SchoolBookingService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SchoolBookingTest extends TestCase
{
    use RefreshDatabase;

    protected User $lead;

    protected Contact $school;

    protected Contact $teacher;

    protected CourseInstance $instance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();

        $this->lead = User::factory()->create(['email' => 'lead@example.com']);
        $this->school = Contact::factory()->school()->create(['email' => 'sekretariat@gs-wald.de', 'name' => 'Grundschule am Wald']);
        $this->teacher = Contact::factory()->teacher($this->school)->create(['email' => 'berg@gs-wald.de', 'name' => 'Frau Berg']);
        $course = Course::factory()->forSchoolClasses()->create(['name' => 'Roboter bauen', 'responsible_user_id' => $this->lead->id]);
        $this->instance = CourseInstance::factory()->for($course)->withDates()->create();
    }

    /**
     * @return array{0: Cart, 1: string}
     */
    protected function cartWithToken(?Contact $contact = null): array
    {
        $plainToken = Str::random(64);
        $cart = Cart::query()->create(['token_hash' => Cart::hashToken($plainToken), 'contact_id' => $contact?->id]);

        return [$cart, $plainToken];
    }

    /**
     * @return array<string, mixed>
     */
    protected function classData(array $overrides = []): array
    {
        return ['class_name' => '4b', 'students' => 24, 'teacher_name' => 'Frau Berg', 'teacher_phone' => '', 'topic' => 'Schwerpunkt Sensoren', 'venue' => '', ...$overrides];
    }

    protected function requestSlot(?CourseInstance $instance = null): Registration
    {
        $cart = Cart::factory()->create();
        $registration = app(SchoolBookingService::class)->hold($instance ?? $this->instance, $cart, $this->teacher);
        app(SchoolBookingService::class)->checkout($cart, [$registration->id => $this->classData()], $this->teacher);

        return $registration->fresh();
    }

    public function test_school_class_instances_always_have_one_slot_without_waitlist(): void
    {
        $this->assertSame(1, $this->instance->capacity);
        $this->assertFalse($this->instance->waitlist_enabled);

        $this->instance->update(['capacity' => 5, 'waitlist_enabled' => true]);

        $this->assertSame(1, $this->instance->fresh()->capacity);
        $this->assertFalse($this->instance->fresh()->waitlist_enabled);
    }

    public function test_slot_list_and_calendar_show_free_slots(): void
    {
        $this->get('/schulen/termine')->assertOk()->assertSee('Roboter bauen')->assertSee('Frei')->assertDontSee('In den Warenkorb');

        $this->actingAs($this->teacher, 'contact')
            ->get('/schulen/termine')
            ->assertSee(route('homepage.schools.cart.add', $this->instance), false);

        $this->getJson('/schulen/kalender/events?start='.now()->toDateString().'&end='.now()->addMonth()->toDateString())
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Roboter bauen')
            ->assertJsonPath('0.extendedProps.availability', SchoolSlotState::Free->getLabel())
            ->assertJsonPath('0.url', route('homepage.schools.slots').'#termin-'.$this->instance->id);

        $this->get('/schulen/kalender')->assertOk()->assertSee(route('homepage.schools.calendar.events'), false);
    }

    public function test_calendar_shows_requested_and_reserved_slots_without_school_names(): void
    {
        $registration = $this->requestSlot();

        $this->getJson('/schulen/kalender/events?start='.now()->toDateString().'&end='.now()->addMonth()->toDateString())
            ->assertJsonPath('0.extendedProps.availability', SchoolSlotState::Requested->getLabel())
            ->assertDontSee('Grundschule am Wald');

        app(SchoolBookingService::class)->approve($registration);

        $this->getJson('/schulen/kalender/events?start='.now()->toDateString().'&end='.now()->addMonth()->toDateString())
            ->assertJsonPath('0.extendedProps.availability', SchoolSlotState::Reserved->getLabel());
    }

    public function test_slot_in_another_cart_is_shown_as_in_cart(): void
    {
        app(SchoolBookingService::class)->hold($this->instance, Cart::factory()->create(), $this->teacher);

        $this->assertSame(SchoolSlotState::InCart, SchoolSlotState::for($this->instance->fresh()));
    }

    public function test_teacher_puts_a_slot_into_the_cart(): void
    {
        [, $token] = $this->cartWithToken();

        $this->actingAs($this->teacher, 'contact')
            ->withCookie(CartManager::COOKIE, $token)
            ->post(route('homepage.schools.cart.add', $this->instance))
            ->assertRedirect(route('homepage.schools.cart'));

        $this->assertSame(1, $this->instance->enrollments()->where('status', EnrollmentStatus::Held)->count());

        // The parents' cart page hands over to the school checkout.
        $this->withCookie(CartManager::COOKIE, $token)->get('/warenkorb')->assertRedirect(route('homepage.schools.cart'));
    }

    public function test_guests_and_parents_cannot_book_school_slots(): void
    {
        $this->post(route('homepage.schools.cart.add', $this->instance))->assertRedirect();
        $this->assertSame(0, $this->instance->enrollments()->count());

        $this->expectException(ReservationException::class);
        app(SchoolBookingService::class)->hold($this->instance, Cart::factory()->create(), Contact::factory()->create());
    }

    public function test_school_slots_cannot_be_booked_through_the_parents_form(): void
    {
        $this->post(route('homepage.cart.add', $this->instance), ['seats' => 1])->assertNotFound();
    }

    public function test_school_slots_and_kids_courses_are_not_mixed_in_one_cart(): void
    {
        $cart = Cart::factory()->create();
        $kidsInstance = CourseInstance::factory()->withDates()->create();
        app(CourseReservationService::class)->hold($kidsInstance, 1, $cart);

        $this->expectException(ReservationException::class);
        app(SchoolBookingService::class)->hold($this->instance, $cart, $this->teacher);
    }

    public function test_a_taken_slot_cannot_be_held_again(): void
    {
        $this->requestSlot();

        $this->expectException(ReservationException::class);
        app(SchoolBookingService::class)->hold($this->instance, Cart::factory()->create(), $this->teacher);
    }

    public function test_checkout_sends_the_request_and_occupies_the_slot(): void
    {
        [$cart, $token] = $this->cartWithToken($this->teacher);
        $registration = app(SchoolBookingService::class)->hold($this->instance, $cart, $this->teacher);

        Livewire::actingAs($this->teacher, 'contact')
            ->withCookies([CartManager::COOKIE => $token])
            ->test(SchoolCheckout::class)
            ->assertSet("classes.{$registration->id}.teacher_name", 'Frau Berg')
            ->set("classes.{$registration->id}.class_name", '4b')
            ->set("classes.{$registration->id}.students", 24)
            ->set("classes.{$registration->id}.topic", 'Schwerpunkt Sensoren')
            ->set('acceptsPrivacy', true)
            ->call('checkout')
            ->assertHasNoErrors()
            ->assertRedirect(route('homepage.schools.cart.done'));

        $registration->refresh();
        $this->assertSame(RegistrationStatus::Requested, $registration->status);
        $this->assertTrue($registration->school->is($this->school));
        $this->assertSame(SchoolVenue::ZLab, $registration->venue);
        $this->assertSame(24, $registration->schoolClass()['students']);
        $this->assertSame(EnrollmentStatus::Requested, $registration->enrollments->first()->status);
        $this->assertSame(0, $this->instance->fresh()->freeSeats());

        Mail::assertSent(SchoolRequestReceivedMail::class, fn (SchoolRequestReceivedMail $mail): bool => $mail->hasTo('berg@gs-wald.de') && $mail->hasCc('sekretariat@gs-wald.de'));
        Mail::assertSent(SchoolRequestMail::class, fn (SchoolRequestMail $mail): bool => $mail->hasTo('lead@example.com'));
    }

    public function test_checkout_requires_class_details_and_venue_if_the_school_may_choose(): void
    {
        $this->instance->course->update(['school_format' => SchoolCourseFormat::Both]);
        [$cart, $token] = $this->cartWithToken($this->teacher);
        $registration = app(SchoolBookingService::class)->hold($this->instance, $cart, $this->teacher);

        Livewire::actingAs($this->teacher, 'contact')
            ->withCookies([CartManager::COOKIE => $token])
            ->test(SchoolCheckout::class)
            ->set("classes.{$registration->id}.class_name", '')
            ->call('checkout')
            ->assertHasErrors(["classes.{$registration->id}.class_name", "classes.{$registration->id}.students", "classes.{$registration->id}.venue", 'acceptsPrivacy']);

        Livewire::actingAs($this->teacher, 'contact')
            ->withCookies([CartManager::COOKIE => $token])
            ->test(SchoolCheckout::class)
            ->set("classes.{$registration->id}.class_name", '4b')
            ->set("classes.{$registration->id}.students", 24)
            ->set("classes.{$registration->id}.venue", SchoolVenue::School->value)
            ->set('acceptsPrivacy', true)
            ->call('checkout')
            ->assertHasNoErrors();

        $this->assertSame(SchoolVenue::School, $registration->fresh()->venue);
        $this->assertSame($this->school->addressLine(), $registration->fresh()->visitAddress());
    }

    public function test_course_lead_approves_via_signed_link(): void
    {
        $registration = $this->requestSlot();
        $showUrl = URL::temporarySignedRoute('approvals.show', now()->addDay(), ['registration' => $registration->id, 'entscheidung' => 'freigeben']);

        $this->get($showUrl)->assertOk()->assertSee('Termin bestätigen')->assertSee('Grundschule am Wald');

        $this->post(URL::temporarySignedRoute('approvals.decide', now()->addHour(), ['registration' => $registration->id, 'entscheidung' => 'freigeben']), ['note' => 'Wir freuen uns!'])
            ->assertRedirect();

        $registration->refresh();
        $this->assertSame(RegistrationStatus::Confirmed, $registration->status);
        $this->assertSame('Wir freuen uns!', $registration->decision_note);
        $this->assertNotNull($registration->staff_remind_at);
        $this->assertSame(EnrollmentStatus::Confirmed, $registration->enrollments->first()->status);
        Mail::assertSent(SchoolRequestDecidedMail::class, fn (SchoolRequestDecidedMail $mail): bool => $mail->approved && $mail->hasTo('berg@gs-wald.de') && $mail->visitUrl !== null);
    }

    public function test_course_lead_declines_in_the_cockpit_and_the_slot_becomes_free(): void
    {
        $registration = $this->requestSlot();
        $this->lead->givePermissionTo(Permission::findOrCreate('ViewAny:Registration'), Permission::findOrCreate('View:Registration'), Permission::findOrCreate('Update:Registration'));

        Livewire::actingAs($this->lead)
            ->test(ViewRegistration::class, ['record' => $registration->id])
            ->assertActionVisible(ApproveSchoolRequestAction::class)
            ->callAction(DeclineSchoolRequestAction::class, ['note' => 'Da sind Ferien.'])
            ->assertNotified();

        $this->assertSame(RegistrationStatus::Declined, $registration->fresh()->status);
        $this->assertSame(1, $this->instance->fresh()->freeSeats());
        Mail::assertSent(SchoolRequestDecidedMail::class, fn (SchoolRequestDecidedMail $mail): bool => ! $mail->approved && $mail->registration->decision_note === 'Da sind Ferien.');
    }

    public function test_cockpit_approval_from_the_registrations_table(): void
    {
        $registration = $this->requestSlot();
        $this->lead->givePermissionTo(Permission::findOrCreate('ViewAny:Registration'), Permission::findOrCreate('Update:Registration'));

        Livewire::actingAs($this->lead)
            ->test(ListRegistrations::class)
            ->callAction(TestAction::make('approveSchoolRequest')->table($registration), ['note' => null])
            ->assertNotified();

        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
    }
}
