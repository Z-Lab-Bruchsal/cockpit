<?php

namespace Tests\Feature\Registrations;

use App\Enums\EnrollmentStatus;
use App\Enums\RegistrationStatus;
use App\Livewire\Homepage\Checkout;
use App\Mail\RegistrationConfirmationMail;
use App\Mail\RegistrationConfirmedMail;
use App\Models\Cart;
use App\Models\Contact;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\Kid;
use App\Models\Registration;
use App\Services\Registrations\CartManager;
use App\Services\Registrations\CourseReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
     * Creates a cart with a known plain token so requests and Livewire tests can use its cookie.
     */
    protected function cartWithToken(?Contact $contact = null): array
    {
        $plainToken = Str::random(64);
        $cart = Cart::query()->create(['token_hash' => Cart::hashToken($plainToken), 'contact_id' => $contact?->id]);

        return [$cart, $plainToken];
    }

    public function test_course_page_offers_registration(): void
    {
        $instance = $this->courseInstance();

        $this->get($instance->course->url())
            ->assertOk()
            ->assertSee('Anmeldung beginnen')
            ->assertSee(route('homepage.cart.add', $instance), false);
    }

    public function test_starting_a_registration_holds_seats_and_sets_cart_cookie(): void
    {
        $instance = $this->courseInstance();

        $response = $this->post(route('homepage.cart.add', $instance), ['seats' => 2]);

        $response->assertRedirect(route('homepage.cart'))->assertCookie(CartManager::COOKIE);
        $this->assertSame(3, $instance->freeSeats());
        $this->assertSame(RegistrationStatus::Held, Registration::query()->sole()->status);
    }

    public function test_full_course_shows_error_on_course_page(): void
    {
        $instance = CourseInstance::factory()->withDates()->create(['capacity' => 1, 'waitlist_enabled' => false]);
        app(CourseReservationService::class)->hold($instance, 1, Cart::factory()->create());

        $this->post(route('homepage.cart.add', $instance), ['seats' => 1])
            ->assertRedirect($instance->course->url().'#durchfuehrung-'.$instance->id)
            ->assertSessionHasErrorsIn('instance'.$instance->id, 'seats');
    }

    public function test_cart_page_and_course_page_show_held_course(): void
    {
        $instance = $this->courseInstance();
        [$cart, $token] = $this->cartWithToken();
        app(CourseReservationService::class)->hold($instance, 1, $cart);

        $this->withCookie(CartManager::COOKIE, $token)
            ->get('/warenkorb')
            ->assertOk()
            ->assertSee($instance->displayTitle())
            ->assertSee('für dich reserviert');

        $this->withCookie(CartManager::COOKIE, $token)
            ->get($instance->course->url())
            ->assertSee('In deinem Warenkorb');
    }

    public function test_guest_checkout_via_cart_page(): void
    {
        $instance = $this->courseInstance();
        [$cart, $token] = $this->cartWithToken();
        $registration = app(CourseReservationService::class)->hold($instance, 2, $cart);

        Livewire::withCookies([CartManager::COOKIE => $token])
            ->test(Checkout::class)
            ->set('name', 'Maria Muster')
            ->set('email', 'maria@example.com')
            ->set("kids.{$registration->id}.0.name", 'Paul')
            ->set("kids.{$registration->id}.0.birthdate", now()->subYears(9)->toDateString())
            ->set("kids.{$registration->id}.1.name", 'Lena')
            ->set("kids.{$registration->id}.1.birthdate", now()->subYears(11)->toDateString())
            ->set('acceptsPrivacy', true)
            ->call('checkout')
            ->assertHasNoErrors()
            ->assertRedirect(route('homepage.cart.done'));

        Mail::assertSent(RegistrationConfirmationMail::class, fn ($mail): bool => $mail->hasTo('maria@example.com'));
        $this->assertSame(RegistrationStatus::PendingConfirmation, $registration->fresh()->status);
    }

    public function test_checkout_validates_children_and_consent(): void
    {
        $instance = $this->courseInstance(minAge: 8, maxAge: 12);
        [$cart, $token] = $this->cartWithToken();
        $registration = app(CourseReservationService::class)->hold($instance, 2, $cart);

        Livewire::withCookies([CartManager::COOKIE => $token])
            ->test(Checkout::class)
            ->set('name', 'Maria')
            ->set('email', 'maria@example.com')
            ->set("kids.{$registration->id}.0.name", 'Paul')
            ->set("kids.{$registration->id}.0.birthdate", now()->subYears(5)->toDateString())
            ->set("kids.{$registration->id}.1.name", '')
            ->call('checkout')
            ->assertHasErrors(['acceptsPrivacy', "kids.{$registration->id}.1.name"]);

        Livewire::withCookies([CartManager::COOKIE => $token])
            ->test(Checkout::class)
            ->set('name', 'Maria')
            ->set('email', 'maria@example.com')
            ->set("kids.{$registration->id}.0.name", 'Paul')
            ->set("kids.{$registration->id}.0.birthdate", now()->subYears(5)->toDateString())
            ->set("kids.{$registration->id}.1.name", 'paul ')
            ->set("kids.{$registration->id}.1.birthdate", now()->subYears(9)->toDateString())
            ->set('acceptsPrivacy', true)
            ->call('checkout')
            ->assertHasErrors(["kids.{$registration->id}.0.birthdate", "kids.{$registration->id}.1.name"]);

        Mail::assertNothingSent();
    }

    public function test_logged_in_parent_picks_own_kids_and_is_confirmed_directly(): void
    {
        $contact = Contact::factory()->create();
        $kid = Kid::factory()->aged(10)->create();
        $contact->kids()->attach($kid);
        $this->actingAs($contact, 'contact');

        $instance = $this->courseInstance();
        [$cart, $token] = $this->cartWithToken($contact);
        $registration = app(CourseReservationService::class)->hold($instance, 1, $cart);

        Livewire::withCookies([CartManager::COOKIE => $token])
            ->test(Checkout::class)
            ->assertSet('email', $contact->email)
            ->set("kids.{$registration->id}.0.kid_id", (string) $kid->id)
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

        [$cart, $token] = $this->cartWithToken($contact);
        $registration = app(CourseReservationService::class)->hold($this->courseInstance(), 1, $cart);

        Livewire::withCookies([CartManager::COOKIE => $token])
            ->test(Checkout::class)
            ->set("kids.{$registration->id}.0.kid_id", (string) $foreignKid->id)
            ->set('acceptsPrivacy', true)
            ->call('checkout')
            ->assertHasErrors("kids.{$registration->id}.0.kid_id");
    }

    public function test_removing_a_course_from_the_cart_frees_seats(): void
    {
        $instance = $this->courseInstance(capacity: 1);
        [$cart, $token] = $this->cartWithToken();
        $registration = app(CourseReservationService::class)->hold($instance, 1, $cart);

        Livewire::withCookies([CartManager::COOKIE => $token])
            ->test(Checkout::class)
            ->call('remove', $registration->id)
            ->assertSee('Dein Warenkorb ist leer');

        $this->assertSame(1, $instance->freeSeats());
    }

    public function test_confirmation_link_needs_a_click_on_the_button(): void
    {
        $instance = $this->courseInstance();
        $cart = Cart::factory()->create();
        $registration = app(CourseReservationService::class)->hold($instance, 1, $cart);
        app(CourseReservationService::class)->checkout($cart, ['email' => 'maria@example.com', 'name' => 'Maria'], [
            $registration->id => [['name' => 'Paul', 'birthdate' => now()->subYears(10)->toDateString()]],
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
        $cart = Cart::factory()->create();
        $registration = app(CourseReservationService::class)->hold($instance, 2, $cart);
        app(CourseReservationService::class)->checkout($cart, ['email' => 'maria@example.com', 'name' => 'Maria'], [
            $registration->id => [
                ['name' => 'Paul', 'birthdate' => now()->subYears(10)->toDateString()],
                ['name' => 'Lena', 'birthdate' => now()->subYears(9)->toDateString()],
            ],
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
        app(CourseReservationService::class)->hold($instance, 1, Cart::factory()->create());
        $this->travel(20)->minutes();

        $this->artisan('app:expire-reservations')->expectsOutputToContain('1 Plätze verfallen')->assertSuccessful();

        $this->assertSame(RegistrationStatus::Expired, Registration::query()->sole()->status);
    }

    public function test_calendar_marks_courses_with_all_seats_in_carts(): void
    {
        $instance = CourseInstance::factory()->withDates(1, now()->addDays(2))->create(['capacity' => 1, 'waitlist_enabled' => false]);
        app(CourseReservationService::class)->hold($instance, 1, Cart::factory()->create());

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
        [$cart, $token] = $this->cartWithToken($contact);
        $registration = app(CourseReservationService::class)->hold($instance, 1, $cart);

        $checkout = Livewire::withCookies([CartManager::COOKIE => $token])
            ->test(Checkout::class)
            ->set("kids.{$registration->id}.0.kid_id", (string) $kid->id)
            ->assertSee('Geburtsdatum von Paul')
            ->set('acceptsPrivacy', true)
            ->call('checkout')
            ->assertHasErrors("kids.{$registration->id}.0.birthdate");

        $checkout
            ->set("kids.{$registration->id}.0.birthdate", now()->subYears(5)->toDateString())
            ->call('checkout')
            ->assertHasErrors("kids.{$registration->id}.0.birthdate");

        $this->assertNull($kid->fresh()->birthdate, 'Nothing is saved if the age does not fit.');

        $checkout
            ->set("kids.{$registration->id}.0.birthdate", now()->subYears(10)->toDateString())
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

        [$cart, $token] = $this->cartWithToken($contact);
        $registration = app(CourseReservationService::class)->hold($this->courseInstance(), 1, $cart);

        Livewire::withCookies([CartManager::COOKIE => $token])
            ->test(Checkout::class)
            ->set("kids.{$registration->id}.0.kid_id", (string) $kid->id)
            ->set('acceptsPrivacy', true)
            ->call('checkout')
            ->assertHasErrors("kids.{$registration->id}.0.birthdate");
    }
}
