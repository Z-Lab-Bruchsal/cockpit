<?php

namespace Tests\Feature\Schools;

use App\Enums\SchoolCourseFormat;
use App\Enums\SchoolVenue;
use App\Filament\Resources\Registrations\Actions\AddInstanceNoteAction;
use App\Filament\Resources\Registrations\Actions\SnoozeStaffReminderAction;
use App\Filament\Resources\Registrations\Pages\ViewRegistration;
use App\Mail\SchoolBookingUpdateMail;
use App\Mail\SchoolVisitReminderMail;
use App\Mail\SchoolVisitStaffReminderMail;
use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\CourseInstanceNote;
use App\Models\Registration;
use App\Models\User;
use App\Services\Schools\SchoolBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SchoolReminderTest extends TestCase
{
    use RefreshDatabase;

    protected User $lead;

    protected Contact $teacher;

    protected Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();

        CompanySetting::current()->update(['teacher_reminder_days' => 14, 'staff_reminder_days' => 21, 'school_reminders_enabled' => true]);

        $this->lead = User::factory()->create(['email' => 'lead@example.com']);
        $this->teacher = Contact::factory()->teacher()->create(['email' => 'berg@gs-wald.de']);
        $this->course = Course::factory()->forSchoolClasses()->create(['responsible_user_id' => $this->lead->id]);
    }

    protected function confirmedBooking(int $daysAhead, array $instanceAttributes = [], ?string $venue = null): Registration
    {
        $instance = CourseInstance::factory()->for($this->course)->withDates(1, now()->addDays($daysAhead)->setTime(9, 0))->create($instanceAttributes);
        $service = app(SchoolBookingService::class);

        [$registration] = $service->hold($instance, $this->teacher);
        $service->checkout($registration, ['class_name' => '4b', 'grade' => 4, 'school_type' => 'primary', 'students' => 24, 'teacher_name' => 'Frau Berg', 'teacher_phone' => '0911 123456', 'venue' => $venue], $this->teacher);
        $service->approve($registration->fresh());

        return $registration->fresh();
    }

    public function test_teacher_and_course_lead_are_reminded_once(): void
    {
        $registration = $this->confirmedBooking(10);

        $this->artisan('app:send-school-reminders')->assertSuccessful();

        Mail::assertSent(SchoolVisitReminderMail::class, fn (SchoolVisitReminderMail $mail): bool => $mail->hasTo('berg@gs-wald.de') && $mail->hasCc($this->teacher->school->email));
        Mail::assertSent(SchoolVisitStaffReminderMail::class, fn (SchoolVisitStaffReminderMail $mail): bool => $mail->hasTo('lead@example.com'));
        $this->assertNotNull($registration->fresh()->teacher_reminded_at);
        $this->assertNotNull($registration->fresh()->staff_reminded_at);

        $this->artisan('app:send-school-reminders');

        Mail::assertSent(SchoolVisitReminderMail::class, 1);
        Mail::assertSent(SchoolVisitStaffReminderMail::class, 1);
    }

    public function test_reminders_wait_until_their_day(): void
    {
        $registration = $this->confirmedBooking(30);

        $this->assertTrue($registration->staff_remind_at->isSameDay(now()->addDays(9)));

        $this->artisan('app:send-school-reminders');

        Mail::assertNotSent(SchoolVisitReminderMail::class);
        Mail::assertNotSent(SchoolVisitStaffReminderMail::class);

        $this->travel(12)->days();
        $this->artisan('app:send-school-reminders');

        Mail::assertNotSent(SchoolVisitReminderMail::class);
        Mail::assertSent(SchoolVisitStaffReminderMail::class);
    }

    public function test_instance_overrides_course_and_course_overrides_company_settings(): void
    {
        $this->course->update(['teacher_reminder_days' => 5]);
        $this->confirmedBooking(10);
        $this->confirmedBooking(10, ['teacher_reminder_days' => 12]);

        $this->artisan('app:send-school-reminders');

        Mail::assertSent(SchoolVisitReminderMail::class, 1);
    }

    public function test_reminders_can_be_switched_off(): void
    {
        $this->course->update(['school_reminders_enabled' => false]);
        $this->confirmedBooking(10);
        $this->confirmedBooking(10, ['school_reminders_enabled' => true]);

        $this->artisan('app:send-school-reminders');

        Mail::assertSent(SchoolVisitReminderMail::class, 1);
        Mail::assertSent(SchoolVisitStaffReminderMail::class, 1);
    }

    public function test_directions_are_attached_only_for_visits_in_the_zlab(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('company/anfahrt.pdf', '%PDF-1.4');
        CompanySetting::current()->update(['directions_document_path' => 'company/anfahrt.pdf']);
        $this->course->update(['school_format' => SchoolCourseFormat::Both]);

        $inZlab = $this->confirmedBooking(10, venue: SchoolVenue::ZLab->value);
        $atSchool = $this->confirmedBooking(10, venue: SchoolVenue::School->value);

        $zlabMail = new SchoolVisitReminderMail($inZlab, 'https://example.com');
        $schoolMail = new SchoolVisitReminderMail($atSchool, 'https://example.com');

        $this->assertCount(1, $zlabMail->attachments());
        $this->assertSame([], $schoolMail->attachments());
        $zlabMail->assertSeeInHtml('erwarten wir Ihre Klasse');
        $schoolMail->assertSeeInHtml('kommen am');
    }

    public function test_course_lead_snoozes_the_reminder_with_a_note(): void
    {
        $registration = $this->confirmedBooking(10);
        $this->artisan('app:send-school-reminders');
        $this->lead->givePermissionTo(Permission::findOrCreate('ViewAny:Registration'), Permission::findOrCreate('View:Registration'), Permission::findOrCreate('Update:Registration'));

        Livewire::actingAs($this->lead)
            ->test(ViewRegistration::class, ['record' => $registration->id])
            ->callAction(SnoozeStaffReminderAction::class, ['remind_at' => now()->addDays(3)->format('Y-m-d H:i:s'), 'note' => 'Schule ruft nochmal wegen Parkplatz an.'])
            ->assertNotified()
            ->callAction(AddInstanceNoteAction::class, ['body' => 'Bus kommt um 8:45.'])
            ->assertNotified();

        $registration->refresh();
        $this->assertNull($registration->staff_reminded_at);
        $this->assertTrue($registration->staff_remind_at->isSameDay(now()->addDays(3)));
        $this->assertSame(2, CourseInstanceNote::query()->where('course_instance_id', $registration->course_instance_id)->where('user_id', $this->lead->id)->count());

        $this->travel(3)->days();
        $this->travel(1)->hours();
        $this->artisan('app:send-school-reminders');

        Mail::assertSent(SchoolVisitStaffReminderMail::class, 2);
        Mail::assertSent(SchoolVisitStaffReminderMail::class, fn (SchoolVisitStaffReminderMail $mail): bool => str_contains($mail->render(), 'Bus kommt um 8:45.'));
    }

    public function test_teacher_confirms_the_visit_via_the_signed_link(): void
    {
        $registration = $this->confirmedBooking(10);
        $url = app(SchoolBookingService::class)->visitUrl($registration);

        $this->get($url)->assertOk()->assertSee('Teilnahme bestätigen')->assertSee('4b');
        $this->get(route('homepage.schools.visit', $registration))->assertForbidden();

        $confirmUrl = URL::temporarySignedRoute('homepage.schools.visit.confirm', now()->addHour(), ['registration' => $registration->id]);

        $this->post($confirmUrl, ['students' => 21, 'remark' => 'Zwei Kinder im Rollstuhl'])->assertRedirect();

        $registration->refresh();
        $this->assertNotNull($registration->teacher_confirmed_at);
        $this->assertSame(21, $registration->schoolClass()['students']);
        Mail::assertSent(SchoolBookingUpdateMail::class, fn (SchoolBookingUpdateMail $mail): bool => $mail->hasTo('lead@example.com')
            && $mail->kind === SchoolBookingUpdateMail::CONFIRMED
            && in_array('Schülerzahl: 24 → 21', $mail->changes, true));
    }
}
