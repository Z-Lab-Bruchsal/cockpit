<?php

namespace Tests\Feature\Registrations;

use App\Enums\EnrollmentStatus;
use App\Enums\RegistrationStatus;
use App\Filament\Resources\CourseInstances\Pages\EditCourseInstance;
use App\Filament\Resources\CourseInstances\RelationManagers\AttendanceRelationManager;
use App\Filament\Resources\CourseInstances\RelationManagers\ParticipantsRelationManager;
use App\Filament\Resources\Kids\Pages\EditKid;
use App\Filament\Resources\Kids\RelationManagers\EnrollmentsRelationManager;
use App\Filament\Resources\Registrations\Pages\ListRegistrations;
use App\Filament\Resources\Registrations\Pages\ViewRegistration;
use App\Mail\RegistrationConfirmedMail;
use App\Models\Attendance;
use App\Models\Cart;
use App\Models\Contact;
use App\Models\CourseInstance;
use App\Models\Enrollment;
use App\Models\Kid;
use App\Models\Registration;
use App\Models\User;
use App\Services\Registrations\CourseReservationService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RegistrationCockpitTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();

        $this->user = User::factory()->create();

        foreach (['Registration', 'CourseInstance', 'Kid', 'Contact'] as $model) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete'] as $ability) {
                $this->user->givePermissionTo(Permission::findOrCreate("{$ability}:{$model}"));
            }
        }

        $this->actingAs($this->user);
    }

    protected function pendingRegistration(): Registration
    {
        $cart = Cart::factory()->create();
        $registration = app(CourseReservationService::class)->hold(CourseInstance::factory()->withDates()->create(), 1, $cart);
        app(CourseReservationService::class)->checkout($cart, ['email' => 'maria@example.com', 'name' => 'Maria'], [
            $registration->id => [['name' => 'Paul', 'birthdate' => now()->subYears(10)->toDateString()]],
        ]);

        return $registration->fresh();
    }

    public function test_pages_render(): void
    {
        $registration = $this->pendingRegistration();

        $this->get('/cockpit/registrations')->assertOk()->assertSee('Paul');
        $this->get("/cockpit/registrations/{$registration->id}")->assertOk()->assertSee('maria@example.com');
        $this->get("/cockpit/course-instances/{$registration->course_instance_id}/edit")->assertOk();
    }

    public function test_cockpit_never_shows_the_entered_birthdate(): void
    {
        $registration = $this->pendingRegistration();
        $birthdate = now()->subYears(10)->toDateString();

        $this->get("/cockpit/registrations/{$registration->id}")
            ->assertSee('10 Jahre')
            ->assertDontSee($birthdate);
    }

    public function test_staff_can_confirm_pending_registration_manually(): void
    {
        $registration = $this->pendingRegistration();

        Livewire::test(ViewRegistration::class, ['record' => $registration->id])
            ->callAction('confirmRegistration')
            ->assertNotified('Anmeldung bestätigt');

        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
        $this->assertSame(1, Contact::query()->count());
        Mail::assertSent(RegistrationConfirmedMail::class);
    }

    public function test_staff_can_cancel_registration(): void
    {
        $registration = $this->pendingRegistration();

        Livewire::test(ListRegistrations::class)
            ->callAction(TestAction::make('cancelRegistration')->table($registration), ['notify' => false])
            ->assertNotified('Anmeldung storniert');

        $this->assertSame(RegistrationStatus::Cancelled, $registration->fresh()->status);
    }

    public function test_staff_can_register_a_kid_and_cancel_it(): void
    {
        $instance = CourseInstance::factory()->withDates()->create(['capacity' => 1]);
        $kid = Kid::factory()->create();
        Contact::factory()->create()->kids()->attach($kid);

        $component = Livewire::test(ParticipantsRelationManager::class, ['ownerRecord' => $instance, 'pageClass' => EditCourseInstance::class])
            ->callAction(TestAction::make('registerKid')->table(), ['kid_id' => $kid->id, 'ignore_capacity' => false])
            ->assertNotified('Angemeldet');

        $enrollment = Enrollment::query()->sole();
        $this->assertSame($kid->id, $enrollment->kid_id);
        $this->assertSame(EnrollmentStatus::Confirmed, $enrollment->status);

        $component
            ->callAction(TestAction::make('cancelEnrollment')->table($enrollment), ['notify' => false])
            ->assertNotified('Abgemeldet');

        $this->assertSame(EnrollmentStatus::Cancelled, $enrollment->fresh()->status);
    }

    public function test_staff_can_take_a_kid_from_the_waitlist(): void
    {
        $instance = CourseInstance::factory()->withDates()->create(['capacity' => 1, 'waitlist_enabled' => true]);
        app(CourseReservationService::class)->registerManually($instance, Kid::factory()->create());
        $waiting = app(CourseReservationService::class)->registerManually($instance, Kid::factory()->create())->enrollments->first();

        $this->assertSame(EnrollmentStatus::Waitlisted, $waiting->status);

        Livewire::test(ParticipantsRelationManager::class, ['ownerRecord' => $instance, 'pageClass' => EditCourseInstance::class])
            ->callAction(TestAction::make('confirmFromWaitlist')->table($waiting));

        $this->assertSame(EnrollmentStatus::Confirmed, $waiting->fresh()->status);
        $this->assertSame(2, $instance->takenSeats());
    }

    public function test_attendance_can_be_ticked_per_date(): void
    {
        $instance = CourseInstance::factory()->withDates(2)->create();
        $enrollment = app(CourseReservationService::class)->registerManually($instance, Kid::factory()->create(['name' => 'Paul']))->enrollments->first();
        $firstDate = $instance->dates->first();

        Livewire::test(AttendanceRelationManager::class, ['ownerRecord' => $instance, 'pageClass' => EditCourseInstance::class])
            ->assertCanSeeTableRecords([$enrollment])
            ->call('updateTableColumnState', 'attendance_'.$firstDate->id, (string) $enrollment->id, true);

        $attendance = Attendance::query()->sole();
        $this->assertTrue($attendance->attended);
        $this->assertSame($firstDate->id, $attendance->course_date_id);
        $this->assertSame($this->user->id, $attendance->checked_by);
    }

    public function test_all_kids_can_be_marked_present_at_once(): void
    {
        $instance = CourseInstance::factory()->withDates(2)->create();
        app(CourseReservationService::class)->registerManually($instance, Kid::factory()->create());
        app(CourseReservationService::class)->registerManually($instance, Kid::factory()->create());

        Livewire::test(AttendanceRelationManager::class, ['ownerRecord' => $instance, 'pageClass' => EditCourseInstance::class])
            ->callAction(TestAction::make('markAllPresent')->table(), ['course_date_id' => $instance->dates->last()->id]);

        $this->assertSame(2, Attendance::query()->where('attended', true)->where('course_date_id', $instance->dates->last()->id)->count());
    }

    public function test_kid_page_shows_course_history_with_attendance(): void
    {
        $kid = Kid::factory()->create();
        $instance = CourseInstance::factory()->withDates(2)->create();
        $enrollment = app(CourseReservationService::class)->registerManually($instance, $kid)->enrollments->first();
        Attendance::factory()->create(['enrollment_id' => $enrollment->id, 'course_date_id' => $instance->dates->first()->id]);

        Livewire::test(EnrollmentsRelationManager::class, ['ownerRecord' => $kid, 'pageClass' => EditKid::class])
            ->assertCanSeeTableRecords([$enrollment])
            ->assertSee('1 / 2');
    }
}
