<?php

namespace Tests\Feature\Registrations;

use App\Enums\EnrollmentStatus;
use App\Mail\RegistrationCancelledMail;
use App\Mail\RegistrationConfirmationMail;
use App\Mail\RegistrationConfirmedMail;
use App\Mail\WaitlistOfferMail;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\Enrollment;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationMailsTest extends TestCase
{
    use RefreshDatabase;

    protected Registration $registration;

    protected function setUp(): void
    {
        parent::setUp();

        $instance = CourseInstance::factory()
            ->for(Course::factory()->create(['name' => 'Lasercutter']))
            ->withDates(2, now()->addWeek()->setTime(16, 0))
            ->create(['title' => 'Herbstferien']);

        $this->registration = Registration::factory()->for($instance, 'courseInstance')->create();
        Enrollment::factory()->for($this->registration)->create(['kid_name' => 'Paul', 'status' => EnrollmentStatus::Confirmed]);
        Enrollment::factory()->for($this->registration)->create(['kid_name' => 'Lena', 'status' => EnrollmentStatus::Waitlisted]);
        $this->registration->load(['courseInstance.course', 'courseInstance.dates', 'enrollments']);
    }

    public function test_confirmation_mail_lists_courses_dates_and_children(): void
    {
        $html = (new RegistrationConfirmationMail(collect([$this->registration]), 'https://example.test/anmeldung/abc', 'Maria', 48))->render();

        $this->assertStringContainsString('Herbstferien', $html);
        $this->assertStringContainsString('(Lasercutter)', $html);
        $this->assertStringContainsString(now()->addWeek()->format('d.m.Y'), $html);
        $this->assertStringContainsString('Paul', $html);
        $this->assertStringContainsString('sobald ein Platz frei wird', $html);
        $this->assertStringContainsString('https://example.test/anmeldung/abc', $html);
        $this->assertStringContainsString('48 Stunden', $html);
    }

    public function test_confirmed_mail_contains_manage_link(): void
    {
        $html = (new RegistrationConfirmedMail(collect([$this->registration]), [$this->registration->id => 'https://example.test/verwalten/xyz']))->render();

        $this->assertStringContainsString('https://example.test/verwalten/xyz', $html);
        $this->assertStringContainsString('Anmeldung ansehen oder stornieren', $html);
    }

    public function test_waitlist_offer_and_cancellation_mails_render(): void
    {
        $offer = (new WaitlistOfferMail($this->registration, 'https://example.test/anmeldung/offer', 24))->render();
        $this->assertStringContainsString('Platz annehmen', $offer);
        $this->assertStringContainsString('24 Stunden', $offer);

        $cancelled = (new RegistrationCancelledMail($this->registration, $this->registration->enrollments->first()))->render();
        $this->assertStringContainsString('Paul', $cancelled);
        $this->assertStringContainsString('storniert', $cancelled);
    }
}
