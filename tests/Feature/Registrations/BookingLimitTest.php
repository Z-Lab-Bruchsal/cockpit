<?php

namespace Tests\Feature\Registrations;

use App\Exceptions\ReservationException;
use App\Filament\Pages\CompanySettingsPage;
use App\Livewire\Homepage\BookingForm;
use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\Kid;
use App\Models\Registration;
use App\Models\User;
use App\Services\Registrations\CourseReservationService;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class BookingLimitTest extends TestCase
{
    use RefreshDatabase;

    protected CourseReservationService $reservations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();
        CompanySetting::current()->update(['booking_limit_per_hour' => 2, 'booking_limit_per_day' => 4, 'max_seats_per_registration' => 5]);
        $this->reservations = app(CourseReservationService::class);
    }

    protected function newInstance(): CourseInstance
    {
        return CourseInstance::factory()
            ->for(Course::factory()->create(['min_age' => null, 'max_age' => null]))
            ->withDates()
            ->create(['capacity' => 10]);
    }

    protected function bookGuest(string $kidName, string $birthdate = '2016-05-01', string $email = 'eltern@example.com', ?CourseInstance $instance = null): void
    {
        [$registration] = $this->reservations->hold($instance ?? $this->newInstance(), 1);
        $this->reservations->checkout($registration, ['email' => $email, 'name' => 'Familie', 'phone' => '0911 123456'], [
            ['name' => $kidName, 'birthdate' => $birthdate],
        ]);
    }

    public function test_child_can_be_booked_for_two_courses_per_hour(): void
    {
        $this->bookGuest('Paul');
        $this->bookGuest('Paul');

        $this->expectException(ReservationException::class);
        $this->expectExceptionMessage('höchstens 2 Kurse pro Stunde');

        $this->bookGuest('paul ');
    }

    public function test_limit_also_applies_with_another_email_address(): void
    {
        $this->bookGuest('Paul', email: 'mama@example.com');
        $this->bookGuest('Paul', email: 'papa@example.com');

        $this->expectException(ReservationException::class);
        $this->bookGuest('Paul', email: 'oma@example.com');
    }

    public function test_siblings_and_other_children_are_counted_separately(): void
    {
        $this->bookGuest('Paul');
        $this->bookGuest('Paul');
        $this->bookGuest('Lena');
        $this->bookGuest('Paul', birthdate: '2019-01-01');

        $this->assertSame(4, Registration::query()->count());
    }

    public function test_limit_resets_after_an_hour(): void
    {
        $this->bookGuest('Paul');
        $this->bookGuest('Paul');

        $this->travel(61)->minutes();
        $this->bookGuest('Paul');

        $this->assertSame(3, Registration::query()->whereNotNull('checked_out_at')->count());
    }

    public function test_third_booking_within_an_hour_is_rejected(): void
    {
        $this->bookGuest('Paul');
        $this->bookGuest('Paul');

        $this->expectException(ReservationException::class);
        $this->bookGuest('Paul');
    }

    public function test_limit_recognises_saved_kids_of_logged_in_parents(): void
    {
        $contact = Contact::factory()->create();
        $kid = Kid::factory()->create(['name' => 'Paul', 'birthdate' => '2016-05-01']);
        $contact->kids()->attach($kid);

        $this->bookGuest('Paul');
        $this->bookGuest('Paul');

        [$registration] = $this->reservations->hold($this->newInstance(), 1, $contact);

        $this->expectException(ReservationException::class);
        $this->reservations->checkout($registration, ['email' => $contact->email, 'name' => $contact->name, 'phone' => '0911 123456'], [
            ['kid_id' => $kid->id],
        ], $contact);
    }

    public function test_checkout_form_shows_the_limit_at_the_child(): void
    {
        $this->bookGuest('Paul');
        $this->bookGuest('Paul');

        [, $token] = $this->reservations->hold($this->newInstance(), 1);

        Livewire::test(BookingForm::class, ['token' => $token])
            ->set('name', 'Familie')
            ->set('email', 'eltern@example.com')
            ->set('phone', '0911 123456')
            ->set('kids.0.name', 'Paul')
            ->set('kids.0.birthdate', '2016-05-01')
            ->set('acceptsPrivacy', true)
            ->call('checkout')
            ->assertHasErrors('kids.0.name')
            ->assertSee('höchstens 2 Kurse pro Stunde');
    }

    public function test_cockpit_registrations_are_not_limited(): void
    {
        $kid = Kid::factory()->create();

        foreach (range(1, 3) as $i) {
            $this->reservations->registerManually($this->newInstance(), $kid);
        }

        $this->assertSame(3, $kid->enrollments()->count());
    }

    public function test_limit_is_configurable(): void
    {
        CompanySetting::current()->update(['booking_limit_per_hour' => 3]);

        $this->bookGuest('Paul');
        $this->bookGuest('Paul');
        $this->bookGuest('Paul');

        $this->expectException(ReservationException::class);
        $this->bookGuest('Paul');
    }

    public function test_hint_is_shown_on_the_course_page_and_the_booking_page(): void
    {
        $instance = $this->newInstance();

        $this->get($instance->course->url())
            ->assertSee('höchstens 2 Kurse pro Stunde und 4 Kurse in 24 Stunden buchbar');

        $this->followingRedirects()
            ->post(route('homepage.booking.start', $instance), ['seats' => 1])
            ->assertSee('höchstens 2 Kurse pro Stunde und 4 Kurse in 24 Stunden buchbar');
    }

    public function test_daily_limit_counts_the_last_24_hours(): void
    {
        $this->travelTo(now()->setTime(20, 0));

        $this->bookGuest('Paul');
        $this->bookGuest('Paul');
        $this->travel(2)->hours();
        $this->bookGuest('Paul');
        $this->bookGuest('Paul');

        // After midnight the calendar day changed, but the last 24 hours still contain four bookings.
        $this->travelTo(now()->addDay()->setTime(8, 0));

        try {
            $this->bookGuest('Paul');
            $this->fail('Expected the 24 hour limit to apply.');
        } catch (ReservationException $exception) {
            $this->assertStringContainsString('höchstens 4 Kurse in 24 Stunden', $exception->getMessage());
            $this->assertStringContainsString('ab 20:00 Uhr', $exception->getMessage());
        }

        $this->travelTo(now()->setTime(20, 1));
        $this->bookGuest('Paul');

        $this->assertSame(5, Registration::query()->whereNotNull('checked_out_at')->count());
    }

    public function test_message_tells_how_long_to_wait(): void
    {
        $this->travelTo(now()->setTime(14, 0));
        $this->bookGuest('Paul');
        $this->travel(12)->minutes();
        $this->bookGuest('Paul');
        $this->travel(25)->minutes();

        try {
            $this->bookGuest('Paul');
            $this->fail('Expected the hourly limit to apply.');
        } catch (ReservationException $exception) {
            $this->assertStringContainsString('höchstens 2 Kurse pro Stunde', $exception->getMessage());
            $this->assertStringContainsString('in 23 Minuten (ab 15:00 Uhr)', $exception->getMessage());
        }
    }

    public function test_running_bookings_do_not_count_towards_the_limit(): void
    {
        $this->reservations->hold($this->newInstance(), 1);
        $this->reservations->hold($this->newInstance(), 1);

        $this->bookGuest('Paul');
        $this->bookGuest('Paul');

        $this->assertSame(2, Registration::query()->whereNotNull('checked_out_at')->count());
    }

    public function test_waiting_text_mentions_the_day_if_not_today(): void
    {
        $this->travelTo(now()->setTime(22, 0));

        $this->assertSame('in 3 Stunden (ab morgen 01:00 Uhr)', CourseReservationService::waitingText(now()->addHours(3)));
        $this->assertSame('in 1 Stunde 30 Minuten (ab 23:30 Uhr)', CourseReservationService::waitingText(now()->addMinutes(90)));
    }

    public function test_hourly_and_daily_limits_can_be_switched_off_separately(): void
    {
        CompanySetting::current()->update(['booking_limit_per_hour' => 0, 'booking_limit_per_day' => 3]);

        $this->bookGuest('Paul');
        $this->bookGuest('Paul');
        $this->bookGuest('Paul');

        $this->expectException(ReservationException::class);
        $this->expectExceptionMessage('höchstens 3 Kurse in 24 Stunden');
        $this->bookGuest('Paul');
    }

    public function test_limits_can_be_switched_off_completely(): void
    {
        CompanySetting::current()->update(['booking_limit_per_hour' => 0, 'booking_limit_per_day' => 0]);

        foreach (range(1, 6) as $i) {
            $this->bookGuest('Paul');
        }

        $this->assertSame(6, Registration::query()->count());
        $this->get($this->newInstance()->course->url())->assertDontSee('buchbar');
    }

    public function test_limit_text_lists_active_limits(): void
    {
        $settings = CompanySetting::current();

        $this->assertSame('höchstens 2 Kurse pro Stunde und 4 Kurse in 24 Stunden', $settings->fill(['booking_limit_per_hour' => 2, 'booking_limit_per_day' => 4])->bookingLimitText());
        $this->assertSame('höchstens 1 Kurs pro Stunde', $settings->fill(['booking_limit_per_hour' => 1, 'booking_limit_per_day' => 0])->bookingLimitText());
        $this->assertSame('höchstens 3 Kurse in 24 Stunden', $settings->fill(['booking_limit_per_hour' => 0, 'booking_limit_per_day' => 3])->bookingLimitText());
        $this->assertSame(1, $settings->fill(['booking_limit_per_hour' => 1, 'booking_limit_per_day' => 3])->strictestBookingLimit());
        $this->assertNull($settings->fill(['booking_limit_per_hour' => 0, 'booking_limit_per_day' => 0])->strictestBookingLimit());
    }

    public function test_maximum_children_per_registration_is_configurable(): void
    {
        CompanySetting::current()->update(['max_seats_per_registration' => 3]);
        $instance = $this->newInstance();

        $this->get($instance->course->url())
            ->assertSee('<option value="3">3</option>', false)
            ->assertDontSee('<option value="4">4</option>', false);

        $this->post(route('homepage.booking.start', $instance), ['seats' => 4])->assertSessionHasErrors('seats');

        $this->expectException(ReservationException::class);
        $this->reservations->hold($instance, 4);
    }

    public function test_limits_can_be_changed_in_the_company_settings(): void
    {
        $this->seed(ShieldSeeder::class);
        $boss = User::factory()->create();
        $boss->assignRole('Z-Lab-Boss');
        $this->actingAs($boss);

        Livewire::test(CompanySettingsPage::class)
            ->fillForm(['booking_limit_per_hour' => 3, 'booking_limit_per_day' => 6, 'max_seats_per_registration' => 3])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = CompanySetting::current();
        $this->assertSame(3, $settings->booking_limit_per_hour);
        $this->assertSame(6, $settings->booking_limit_per_day);
        $this->assertSame(3, $settings->max_seats_per_registration);
    }
}
