<?php

namespace Tests\Feature\Registrations;

use App\Enums\EnrollmentStatus;
use App\Enums\RegistrationStatus;
use App\Livewire\Homepage\BookingForm;
use App\Mail\RegistrationConfirmationMail;
use App\Mail\RegistrationConfirmedMail;
use App\Models\Contact;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\Kid;
use App\Models\Registration;
use App\Services\Registrations\CourseReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();
    }

    protected function courseInstance(int $capacity = 5, ?int $minAge = 8, ?int $maxAge = 14): CourseInstance
    {
        $course = Course::factory()->create(['min_age' => $minAge, 'max_age' => $maxAge]);

        return CourseInstance::factory()->for($course)->withDates(2)->create(['capacity' => $capacity]);
    }

    /**
     * Starts a booking directly via the service and returns the registration and the plain token of the booking page.
     *
     * @return array{0: Registration, 1: string}
     */
    protected function startBooking(CourseInstance $instance, int $seats = 1, ?Contact $contact = null): array
    {
        return app(CourseReservationService::class)->hold($instance, $seats, $contact);
    }

    public function test_course_page_offers_booking_with_the_time_limit(): void
    {
        $instance = $this->courseInstance();

        $this->get($instance->course->url())
            ->assertOk()
            ->assertSee('Buchen')
            ->assertSee('15 Minuten für dich reserviert')
            ->assertSee(route('homepage.booking.start', $instance), false);
    }

    public function test_booking_holds_seats_and_opens_the_booking_page_with_countdown(): void
    {
        $instance = $this->courseInstance();

        $response = $this->post(route('homepage.booking.start', $instance), ['seats' => 2]);

        $registration = Registration::query()->sole();
        $this->assertSame(RegistrationStatus::Held, $registration->status);
        $this->assertSame(3, $instance->freeSeats());
        $this->assertNotNull($registration->hold_token_hash);

        $response->assertRedirect();
        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee($instance->displayTitle())
            ->assertSee('role="timer"', false)
            ->assertSee('Minuten, um die Buchung abzuschicken');
    }

    public function test_clicking_book_twice_continues_the_running_booking(): void
    {
        $instance = $this->courseInstance();

        $first = $this->post(route('homepage.booking.start', $instance), ['seats' => 1])->headers->get('Location');
        $second = $this->post(route('homepage.booking.start', $instance), ['seats' => 1])->headers->get('Location');

        $this->assertSame($first, $second);
        $this->assertSame(1, Registration::query()->count());
    }

    public function test_full_course_shows_error_on_course_page(): void
    {
        $instance = CourseInstance::factory()->withDates()->create(['capacity' => 1, 'waitlist_enabled' => false]);
        $this->startBooking($instance);

        $this->post(route('homepage.booking.start', $instance), ['seats' => 1])
            ->assertRedirect($instance->course->url().'#durchfuehrung-'.$instance->id)
            ->assertSessionHasErrorsIn('instance'.$instance->id, 'seats');
    }

    public function test_school_accounts_cannot_book_courses_for_kids(): void
    {
        $instance = $this->courseInstance();
        $this->actingAs(Contact::factory()->school()->create(), 'contact');

        $this->get($instance->course->url())
            ->assertSee('Mit einem Schulkonto können keine Kurse für einzelne Kinder gebucht werden')
            ->assertDontSee(route('homepage.booking.start', $instance), false);

        $this->post(route('homepage.booking.start', $instance), ['seats' => 1])
            ->assertRedirect($instance->course->url().'#durchfuehrung-'.$instance->id)
            ->assertSessionHasErrorsIn('instance'.$instance->id, 'seats');

        $this->assertSame(0, Registration::query()->count());
    }

    public function test_school_class_courses_cannot_be_booked_as_parent(): void
    {
        $course = Course::factory()->forSchoolClasses()->create();
        $instance = CourseInstance::factory()->for($course)->withDates()->create();

        $this->get($course->url())
            ->assertSee('Dieser Kurs ist für Schulklassen')
            ->assertDontSee(route('homepage.booking.start', $instance), false);

        $this->post(route('homepage.booking.start', $instance), ['seats' => 1])->assertNotFound();

        [, $token] = app(CourseReservationService::class)->hold($instance, 1);
        $this->get(route('homepage.booking.show', $token))->assertNotFound();
    }

    public function test_guest_checkout_on_the_booking_page(): void
    {
        $instance = $this->courseInstance();
        [$registration, $token] = $this->startBooking($instance, 2);

        Livewire::test(BookingForm::class, ['token' => $token])
            ->set('name', 'Maria Muster')
            ->set('email', 'maria@example.com')
            ->set('phone', '0911 / 123 45-6')
            ->set('kids.0.name', 'Paul')
            ->set('kids.0.birthdate', now()->subYears(9)->toDateString())
            ->set('kids.1.name', 'Lena')
            ->set('kids.1.birthdate', now()->subYears(11)->toDateString())
            ->set('acceptsPrivacy', true)
            ->call('checkout')
            ->assertHasNoErrors()
            ->assertRedirect(route('homepage.booking.done'));

        Mail::assertSent(RegistrationConfirmationMail::class, fn ($mail): bool => $mail->hasTo('maria@example.com'));
        $registration->refresh();
        $this->assertSame(RegistrationStatus::PendingConfirmation, $registration->status);
        $this->assertSame('0911 / 123 45-6', $registration->phone);
        $this->assertNull($registration->hold_token_hash);
    }

    public function test_phone_number_is_required_and_checked(): void
    {
        [, $token] = $this->startBooking($this->courseInstance());

        $form = Livewire::test(BookingForm::class, ['token' => $token])
            ->set('name', 'Maria')
            ->set('email', 'maria@example.com')
            ->set('kids.0.name', 'Paul')
            ->set('kids.0.birthdate', now()->subYears(10)->toDateString())
            ->set('acceptsPrivacy', true)
            ->call('checkout')
            ->assertHasErrors(['phone' => 'required'])
            ->assertSee('Bitte gib eine Telefonnummer für Rückfragen an.');

        $form->set('phone', 'ruft mich an')
            ->call('checkout')
            ->assertHasErrors('phone');

        $form->set('phone', '12345')
            ->call('checkout')
            ->assertHasErrors('phone');

        $form->set('phone', '+49 911 123456')
            ->call('checkout')
            ->assertHasNoErrors();

        Mail::assertSent(RegistrationConfirmationMail::class);
    }

    public function test_sending_after_the_time_ran_out_fails_and_seats_are_free(): void
    {
        $instance = $this->courseInstance(capacity: 1);
        [$registration, $token] = $this->startBooking($instance);

        $form = Livewire::test(BookingForm::class, ['token' => $token])
            ->set('name', 'Maria')
            ->set('email', 'maria@example.com')
            ->set('phone', '0911 123456')
            ->set('kids.0.name', 'Paul')
            ->set('kids.0.birthdate', now()->subYears(10)->toDateString())
            ->set('acceptsPrivacy', true);

        $this->travel(16)->minutes();

        $form->call('checkout')->assertRedirect(route('homepage.booking.show', $token));

        $this->assertSame(1, $instance->freeSeats());
        $this->assertSame(RegistrationStatus::Held, $registration->fresh()->status);
        Mail::assertNothingSent();

        $this->get(route('homepage.booking.show', $token))
            ->assertOk()
            ->assertSee('Die Zeit ist abgelaufen')
            ->assertSee($instance->course->url().'#durchfuehrung-'.$instance->id, false);
    }

    public function test_unknown_booking_token_is_not_found(): void
    {
        $this->get(route('homepage.booking.show', str_repeat('a', 64)))->assertNotFound();
    }

    public function test_checkout_validates_children_and_consent(): void
    {
        $instance = $this->courseInstance(minAge: 8, maxAge: 12);
        [, $token] = $this->startBooking($instance, 2);

        Livewire::test(BookingForm::class, ['token' => $token])
            ->set('name', 'Maria')
            ->set('email', 'maria@example.com')
            ->set('phone', '0911 123456')
            ->set('kids.0.name', 'Paul')
            ->set('kids.0.birthdate', now()->subYears(5)->toDateString())
            ->set('kids.1.name', '')
            ->call('checkout')
            ->assertHasErrors(['acceptsPrivacy', 'kids.1.name']);

        Livewire::test(BookingForm::class, ['token' => $token])
            ->set('name', 'Maria')
            ->set('email', 'maria@example.com')
            ->set('phone', '0911 123456')
            ->set('kids.0.name', 'Paul')
            ->set('kids.0.birthdate', now()->subYears(5)->toDateString())
            ->set('kids.1.name', 'paul ')
            ->set('kids.1.birthdate', now()->subYears(9)->toDateString())
            ->set('acceptsPrivacy', true)
            ->call('checkout')
            ->assertHasErrors(['kids.0.birthdate', 'kids.1.name']);

        Mail::assertNothingSent();
    }

    public function test_child_already_booked_for_the_course_cannot_be_booked_again(): void
    {
        $instance = $this->courseInstance();
        $birthdate = now()->subYears(10)->toDateString();
        [$first] = $this->startBooking($instance);
        app(CourseReservationService::class)->checkout($first, ['email' => 'maria@example.com', 'name' => 'Maria', 'phone' => '0911 123456'], [['name' => 'Paul', 'birthdate' => $birthdate]]);

        [, $token] = $this->startBooking($instance);

        Livewire::test(BookingForm::class, ['token' => $token])
            ->set('name', 'Maria')
            ->set('email', 'maria@example.com')
            ->set('phone', '0911 123456')
            ->set('kids.0.name', 'paul')
            ->set('kids.0.birthdate', $birthdate)
            ->set('acceptsPrivacy', true)
            ->call('checkout')
            ->assertHasErrors('kids.0.name')
            ->assertSee('Dieses Kind ist für diesen Kurs schon angemeldet.');
    }

    public function test_logged_in_parent_picks_own_kids_and_is_confirmed_directly(): void
    {
        $contact = Contact::factory()->create(['phone' => '0911 123456']);
        $kid = Kid::factory()->aged(10)->create();
        $contact->kids()->attach($kid);
        $this->actingAs($contact, 'contact');

        $instance = $this->courseInstance();
        [$registration, $token] = $this->startBooking($instance, 1, $contact);

        Livewire::test(BookingForm::class, ['token' => $token])
            ->assertSet('email', $contact->email)
            ->assertSet('phone', '0911 123456')
            ->set('kids.0.kid_id', (string) $kid->id)
            ->set('acceptsPrivacy', true)
            ->call('checkout')
            ->assertHasNoErrors();

        Mail::assertNotSent(RegistrationConfirmationMail::class);
        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);

        $this->get('/konto')->assertSee($instance->displayTitle())->assertSee($kid->name);
    }

    public function test_parent_cannot_pick_foreign_kids(): void
    {
        $contact = Contact::factory()->create();
        $this->actingAs($contact, 'contact');
        $foreignKid = Kid::factory()->create();

        [, $token] = $this->startBooking($this->courseInstance(), 1, $contact);

        Livewire::test(BookingForm::class, ['token' => $token])
            ->set('kids.0.kid_id', (string) $foreignKid->id)
            ->set('acceptsPrivacy', true)
            ->call('checkout')
            ->assertHasErrors('kids.0.kid_id');
    }

    public function test_cancelling_a_booking_frees_the_seats(): void
    {
        $instance = $this->courseInstance(capacity: 1);
        [$registration, $token] = $this->startBooking($instance);

        $this->get(route('homepage.booking.show', $token))->assertSee(route('homepage.booking.cancel', $token), false);

        $this->post(route('homepage.booking.cancel', $token))
            ->assertRedirect($instance->course->url().'#durchfuehrung-'.$instance->id)
            ->assertSessionHas('status');

        $this->assertSame(1, $instance->freeSeats());
        $this->assertSame(RegistrationStatus::Cancelled, $registration->fresh()->status);
        $this->post(route('homepage.booking.cancel', $token))->assertNotFound();
    }

    public function test_confirmation_link_needs_a_click_on_the_button(): void
    {
        $instance = $this->courseInstance();
        [$registration] = $this->startBooking($instance);
        app(CourseReservationService::class)->checkout($registration, ['email' => 'maria@example.com', 'name' => 'Maria', 'phone' => '0911 123456'], [
            ['name' => 'Paul', 'birthdate' => now()->subYears(10)->toDateString()],
        ]);
        $token = basename(parse_url(Mail::sent(RegistrationConfirmationMail::class)->last()->confirmationUrl, PHP_URL_PATH));

        $this->get("/anmeldung/{$token}")->assertOk()->assertSee('Jetzt verbindlich bestätigen');
        $this->assertSame(RegistrationStatus::PendingConfirmation, $registration->fresh()->status);

        $this->post("/anmeldung/{$token}")->assertRedirect("/anmeldung/{$token}");
        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);

        $this->get("/anmeldung/{$token}")->assertSee('Vielen Dank');
        $this->get("/anmeldung/{$token}")->assertSee('Link nicht mehr gültig');
    }

    public function test_parents_can_cancel_via_manage_link(): void
    {
        $instance = $this->courseInstance();
        [$registration] = $this->startBooking($instance, 2);
        app(CourseReservationService::class)->checkout($registration, ['email' => 'maria@example.com', 'name' => 'Maria', 'phone' => '0911 123456'], [
            ['name' => 'Paul', 'birthdate' => now()->subYears(10)->toDateString()],
            ['name' => 'Lena', 'birthdate' => now()->subYears(9)->toDateString()],
        ]);
        app(CourseReservationService::class)->confirm(basename(parse_url(Mail::sent(RegistrationConfirmationMail::class)->last()->confirmationUrl, PHP_URL_PATH)));

        $manageUrl = Mail::sent(RegistrationConfirmedMail::class)->last()->manageUrls[$registration->id];
        $manageToken = basename(parse_url($manageUrl, PHP_URL_PATH));

        $this->get("/anmeldung/verwalten/{$manageToken}")->assertOk()->assertSee('Paul')->assertSee('Lena');

        $paul = $registration->enrollments()->where('kid_name', 'Paul')->sole();
        $this->post("/anmeldung/verwalten/{$manageToken}", ['enrollment' => $paul->id])->assertRedirect();

        $this->assertSame(EnrollmentStatus::Cancelled, $paul->fresh()->status);
        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);

        $this->post("/anmeldung/verwalten/{$manageToken}")->assertRedirect();
        $this->assertSame(RegistrationStatus::Cancelled, $registration->fresh()->status);

        $this->get('/anmeldung/verwalten/falsch')->assertNotFound();
    }

    public function test_parent_can_cancel_from_account_but_not_foreign_registrations(): void
    {
        $contact = Contact::factory()->create();
        $kid = Kid::factory()->create();
        $contact->kids()->attach($kid);
        $own = app(CourseReservationService::class)->registerManually($this->courseInstance(), $kid, $contact);
        $foreign = app(CourseReservationService::class)->registerManually($this->courseInstance(), Kid::factory()->create(), Contact::factory()->create());

        $this->actingAs($contact, 'contact');

        $this->post(route('homepage.account.registration.cancel', $foreign))->assertNotFound();
        $this->post(route('homepage.account.registration.cancel', $own))->assertRedirect('/konto');

        $this->assertSame(RegistrationStatus::Cancelled, $own->fresh()->status);
        $this->assertSame(RegistrationStatus::Confirmed, $foreign->fresh()->status);
    }

    public function test_expire_command_releases_overdue_holds(): void
    {
        $instance = $this->courseInstance(capacity: 1);
        $this->startBooking($instance);
        $this->travel(20)->minutes();

        $this->artisan('app:expire-reservations')->expectsOutputToContain('1 Plätze verfallen')->assertSuccessful();

        $this->assertSame(RegistrationStatus::Expired, Registration::query()->sole()->status);
    }

    public function test_calendar_marks_courses_with_all_seats_in_running_bookings(): void
    {
        $instance = CourseInstance::factory()->withDates(1, now()->addDays(2))->create(['capacity' => 1, 'waitlist_enabled' => false]);
        $this->startBooking($instance);

        $this->getJson('/kalender/events?'.http_build_query(['start' => now()->toIso8601String(), 'end' => now()->addMonth()->toIso8601String()]))
            ->assertJsonPath('0.extendedProps.availability', 'Gerade alle Plätze in Reservierung');
    }

    public function test_saved_kid_without_birthdate_needs_one_at_checkout(): void
    {
        $contact = Contact::factory()->create();
        $kid = Kid::factory()->create(['name' => 'Paul', 'birthdate' => null]);
        $contact->kids()->attach($kid);
        $this->actingAs($contact, 'contact');

        $instance = $this->courseInstance(minAge: 8, maxAge: 12);
        [$registration, $token] = $this->startBooking($instance, 1, $contact);

        $checkout = Livewire::test(BookingForm::class, ['token' => $token])
            ->set('phone', '0911 123456')
            ->set('kids.0.kid_id', (string) $kid->id)
            ->assertSee('Geburtsdatum von Paul')
            ->set('acceptsPrivacy', true)
            ->call('checkout')
            ->assertHasErrors('kids.0.birthdate');

        $checkout
            ->set('kids.0.birthdate', now()->subYears(5)->toDateString())
            ->call('checkout')
            ->assertHasErrors('kids.0.birthdate');

        $this->assertNull($kid->fresh()->birthdate, 'Nothing is saved if the age does not fit.');

        $checkout
            ->set('kids.0.birthdate', now()->subYears(10)->toDateString())
            ->call('checkout')
            ->assertHasNoErrors();

        $this->assertSame(10, $kid->fresh()->age());
        $this->assertFalse($kid->fresh()->birthdate_estimated);
        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
    }

    public function test_saved_kid_with_estimated_birthdate_also_needs_the_exact_one(): void
    {
        $contact = Contact::factory()->create();
        $kid = Kid::factory()->create(['name' => 'Lena'])->estimateBirthdateFromAge(10);
        $kid->save();
        $contact->kids()->attach($kid);
        $this->actingAs($contact, 'contact');

        [, $token] = $this->startBooking($this->courseInstance(), 1, $contact);

        Livewire::test(BookingForm::class, ['token' => $token])
            ->set('kids.0.kid_id', (string) $kid->id)
            ->set('acceptsPrivacy', true)
            ->call('checkout')
            ->assertHasErrors('kids.0.birthdate');
    }
}
