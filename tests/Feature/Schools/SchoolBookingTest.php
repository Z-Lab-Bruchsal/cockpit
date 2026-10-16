<?php

namespace Tests\Feature\Schools;

use App\Enums\EnrollmentStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SchoolCourseFormat;
use App\Enums\SchoolSlotState;
use App\Enums\SchoolType;
use App\Enums\SchoolVenue;
use App\Exceptions\ReservationException;
use App\Filament\Resources\Registrations\Actions\ApproveSchoolRequestAction;
use App\Filament\Resources\Registrations\Actions\DeclineSchoolRequestAction;
use App\Filament\Resources\Registrations\Pages\ListRegistrations;
use App\Filament\Resources\Registrations\Pages\ViewRegistration;
use App\Livewire\Schools\SchoolBookingForm;
use App\Mail\SchoolRequestDecidedMail;
use App\Mail\SchoolRequestMail;
use App\Mail\SchoolRequestReceivedMail;
use App\Models\Contact;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\Registration;
use App\Models\User;
use App\Services\Schools\SchoolBookingService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
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
        $course = Course::factory()->forSchoolClasses()->create([
            'name' => 'Roboter bauen',
            'responsible_user_id' => $this->lead->id,
            'school_types' => [SchoolType::Primary, SchoolType::SpecialNeeds],
            'min_grade' => 3,
            'max_grade' => 4,
            'max_class_size' => 28,
        ]);
        $this->instance = CourseInstance::factory()->for($course)->withDates()->create();
    }

    /**
     * @return array<string, mixed>
     */
    protected function classData(array $overrides = []): array
    {
        return ['class_name' => '4b', 'grade' => 4, 'school_type' => SchoolType::Primary->value, 'students' => 24, 'teacher_name' => 'Frau Berg', 'teacher_phone' => '0911 123456', 'topic' => 'Schwerpunkt Sensoren', 'venue' => '', ...$overrides];
    }

    protected function requestSlot(?CourseInstance $instance = null): Registration
    {
        [$registration] = app(SchoolBookingService::class)->hold($instance ?? $this->instance, $this->teacher);

        return app(SchoolBookingService::class)->checkout($registration, $this->classData(), $this->teacher);
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
        $this->get('/schulen/termine')
            ->assertOk()
            ->assertSee('Roboter bauen')
            ->assertSee('Klasse 3–4 · Grundschule, Förderschule · max. 28 Kinder')
            ->assertSee('Frei')
            ->assertDontSee(route('homepage.schools.booking.start', $this->instance), false);

        $this->actingAs($this->teacher, 'contact')
            ->get('/schulen/termine')
            ->assertSee(route('homepage.schools.booking.start', $this->instance), false);

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

    public function test_slot_being_booked_by_another_school_is_shown_as_such(): void
    {
        app(SchoolBookingService::class)->hold($this->instance, $this->teacher);

        $this->assertSame(SchoolSlotState::BeingBooked, SchoolSlotState::for($this->instance->fresh()));
    }

    public function test_teacher_books_a_slot_and_sees_the_countdown(): void
    {
        $response = $this->actingAs($this->teacher, 'contact')
            ->post(route('homepage.schools.booking.start', $this->instance));

        $this->assertSame(1, $this->instance->enrollments()->where('status', EnrollmentStatus::Held)->count());

        $response->assertRedirect();
        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Termin buchen')
            ->assertSee('role="timer"', false)
            ->assertSee('Danach wird der Termin wieder freigegeben.')
            ->assertSee('Höchstens 28 Kinder');
    }

    public function test_guests_and_parents_cannot_book_school_slots(): void
    {
        $this->post(route('homepage.schools.booking.start', $this->instance))->assertRedirect(route('homepage.login', ['weiter' => '/schulen/buchen/'.$this->instance->id]));

        $this->actingAs(Contact::factory()->create(), 'contact')
            ->post(route('homepage.schools.booking.start', $this->instance))
            ->assertRedirect(route('homepage.schools.index'));

        $this->assertSame(0, $this->instance->enrollments()->count());

        $this->expectException(ReservationException::class);
        app(SchoolBookingService::class)->hold($this->instance, Contact::factory()->create());
    }

    public function test_school_slots_cannot_be_booked_through_the_parents_form(): void
    {
        $this->post(route('homepage.booking.start', $this->instance), ['seats' => 1])->assertNotFound();
    }

    public function test_other_schools_cannot_open_a_running_booking(): void
    {
        [, $token] = app(SchoolBookingService::class)->hold($this->instance, $this->teacher);
        $otherSchool = Contact::factory()->school()->create();

        $this->actingAs($otherSchool, 'contact')->get(route('homepage.schools.booking.show', $token))->assertNotFound();
        $this->actingAs($otherSchool, 'contact')->post(route('homepage.schools.booking.cancel', $token))->assertNotFound();
    }

    public function test_a_taken_slot_cannot_be_held_again(): void
    {
        $this->requestSlot();

        $this->expectException(ReservationException::class);
        app(SchoolBookingService::class)->hold($this->instance, $this->teacher);
    }

    public function test_checkout_sends_the_request_and_occupies_the_slot(): void
    {
        [$registration, $token] = app(SchoolBookingService::class)->hold($this->instance, $this->teacher);

        Livewire::actingAs($this->teacher, 'contact')
            ->test(SchoolBookingForm::class, ['token' => $token])
            ->assertSet('teacherName', 'Frau Berg')
            ->set('className', '4b')
            ->set('schoolType', SchoolType::Primary->value)
            ->set('grade', '4')
            ->set('students', '24')
            ->set('teacherPhone', '0911 123456')
            ->set('topic', 'Schwerpunkt Sensoren')
            ->set('acceptsPrivacy', true)
            ->call('checkout')
            ->assertHasNoErrors()
            ->assertRedirect(route('homepage.schools.booking.done'));

        $registration->refresh();
        $this->assertSame(RegistrationStatus::Requested, $registration->status);
        $this->assertTrue($registration->school->is($this->school));
        $this->assertSame(SchoolVenue::ZLab, $registration->venue);
        $this->assertSame(24, $registration->schoolClass()['students']);
        $this->assertSame(4, $registration->schoolClass()['grade']);
        $this->assertSame(SchoolType::Primary, $registration->schoolClass()['school_type']);
        $this->assertSame('4. Klasse, Grundschule', $registration->schoolClassLevel());
        $this->assertSame('0911 123456', $registration->phone);
        $this->assertNull($registration->hold_token_hash);
        $this->assertSame(EnrollmentStatus::Requested, $registration->enrollments->first()->status);
        $this->assertSame(0, $this->instance->fresh()->freeSeats());

        Mail::assertSent(SchoolRequestReceivedMail::class, fn (SchoolRequestReceivedMail $mail): bool => $mail->hasTo('berg@gs-wald.de') && $mail->hasCc('sekretariat@gs-wald.de'));
        Mail::assertSent(SchoolRequestMail::class, fn (SchoolRequestMail $mail): bool => $mail->hasTo('lead@example.com') && str_contains($mail->render(), '4. Klasse, Grundschule'));
    }

    public function test_checkout_requires_class_details_phone_and_venue_if_the_school_may_choose(): void
    {
        $this->instance->course->update(['school_format' => SchoolCourseFormat::Both]);
        [$registration, $token] = app(SchoolBookingService::class)->hold($this->instance, $this->teacher);

        Livewire::actingAs($this->teacher, 'contact')
            ->test(SchoolBookingForm::class, ['token' => $token])
            ->set('className', '')
            ->set('teacherPhone', '')
            ->call('checkout')
            ->assertHasErrors(['className', 'grade', 'schoolType', 'students', 'venue', 'teacherPhone', 'acceptsPrivacy'])
            ->assertSee('Bitte geben Sie eine Telefonnummer für Rückfragen an.');

        Livewire::actingAs($this->teacher, 'contact')
            ->test(SchoolBookingForm::class, ['token' => $token])
            ->set('className', '4b')
            ->set('schoolType', SchoolType::Primary->value)
            ->set('grade', '4')
            ->set('students', '24')
            ->set('teacherPhone', '0911 123456')
            ->set('venue', SchoolVenue::School->value)
            ->set('acceptsPrivacy', true)
            ->call('checkout')
            ->assertHasNoErrors();

        $this->assertSame(SchoolVenue::School, $registration->fresh()->venue);
        $this->assertSame($this->school->addressLine(), $registration->fresh()->visitAddress());
    }

    public function test_class_size_grade_and_school_type_must_fit_the_course(): void
    {
        [, $token] = app(SchoolBookingService::class)->hold($this->instance, $this->teacher);

        Livewire::actingAs($this->teacher, 'contact')
            ->test(SchoolBookingForm::class, ['token' => $token])
            ->set('className', '7a')
            ->set('schoolType', SchoolType::Grammar->value)
            ->set('grade', '7')
            ->set('students', '29')
            ->set('teacherPhone', '0911 123456')
            ->set('acceptsPrivacy', true)
            ->call('checkout')
            ->assertHasErrors(['schoolType', 'grade', 'students'])
            ->assertSee('Für diesen Kurs sind höchstens 28 Kinder möglich.');

        $this->assertSame(RegistrationStatus::Held, Registration::query()->sole()->status);
    }

    public function test_class_size_can_be_overridden_per_instance(): void
    {
        $this->instance->update(['max_class_size' => 20]);
        [$registration] = app(SchoolBookingService::class)->hold($this->instance, $this->teacher);

        try {
            app(SchoolBookingService::class)->checkout($registration, $this->classData(['students' => 21]), $this->teacher);
            $this->fail('Expected the class size of the instance to apply.');
        } catch (ReservationException $exception) {
            $this->assertStringContainsString('höchstens 20 Kinder', $exception->getMessage());
        }

        $this->instance->update(['max_class_size' => null]);
        $this->instance->course->update(['max_class_size' => null]);

        $this->assertSame(RegistrationStatus::Requested, app(SchoolBookingService::class)->checkout($registration, $this->classData(['students' => 60]), $this->teacher)->status);
    }

    public function test_courses_without_limits_accept_every_school_type_and_grade(): void
    {
        $this->instance->course->update(['school_types' => null, 'min_grade' => null, 'max_grade' => null]);
        [$registration] = app(SchoolBookingService::class)->hold($this->instance, $this->teacher);

        $registration = app(SchoolBookingService::class)->checkout($registration, $this->classData(['school_type' => SchoolType::Vocational->value, 'grade' => 12]), $this->teacher);

        $this->assertSame('12. Klasse, Berufliche Schule', $registration->schoolClassLevel());
    }

    public function test_school_type_is_prefilled_from_the_last_booking(): void
    {
        $this->requestSlot();
        [, $token] = app(SchoolBookingService::class)->hold(CourseInstance::factory()->for($this->instance->course)->withDates()->create(), $this->teacher);

        Livewire::actingAs($this->teacher, 'contact')
            ->test(SchoolBookingForm::class, ['token' => $token])
            ->assertSet('schoolType', SchoolType::Primary->value);
    }

    public function test_sending_after_the_time_ran_out_fails(): void
    {
        [, $token] = app(SchoolBookingService::class)->hold($this->instance, $this->teacher);

        $form = Livewire::actingAs($this->teacher, 'contact')
            ->test(SchoolBookingForm::class, ['token' => $token]);

        $this->travel(16)->minutes();

        $form->call('checkout')->assertRedirect(route('homepage.schools.booking.show', $token));

        $this->assertSame(1, $this->instance->fresh()->freeSeats());
        $this->actingAs($this->teacher, 'contact')
            ->get(route('homepage.schools.booking.show', $token))
            ->assertOk()
            ->assertSee('Der Termin wurde wieder freigegeben')
            ->assertSee(route('homepage.schools.slots').'#termin-'.$this->instance->id, false);
    }

    public function test_cancelling_a_booking_frees_the_slot(): void
    {
        [, $token] = app(SchoolBookingService::class)->hold($this->instance, $this->teacher);

        $this->actingAs($this->teacher, 'contact')
            ->post(route('homepage.schools.booking.cancel', $token))
            ->assertRedirect(route('homepage.schools.slots'));

        $this->assertSame(1, $this->instance->fresh()->freeSeats());
    }

    public function test_visit_confirmation_respects_the_class_size(): void
    {
        $registration = $this->requestSlot();
        app(SchoolBookingService::class)->approve($registration);
        $url = URL::temporarySignedRoute('homepage.schools.visit.confirm', now()->addHour(), ['registration' => $registration->id]);

        $this->post($url, ['students' => 29])->assertSessionHasErrors('students');
        $this->post($url, ['students' => 28])->assertSessionHasNoErrors();
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
