<?php

namespace Tests\Feature\Schools;

use App\Enums\SchoolCourseFormat;
use App\Filament\Pages\CompanySettingsPage;
use App\Filament\Resources\Contacts\Pages\EditContact;
use App\Filament\Resources\Contacts\RelationManagers\TeachersRelationManager;
use App\Filament\Resources\CourseInstances\Pages\EditCourseInstance;
use App\Filament\Resources\CourseInstances\RelationManagers\NotesRelationManager;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Mail\SchoolBookingUpdateMail;
use App\Mail\SchoolLoginLinkMail;
use App\Mail\SchoolRequestDecidedMail;
use App\Mail\SchoolRequestMail;
use App\Mail\SchoolRequestReceivedMail;
use App\Mail\SchoolVisitReminderMail;
use App\Mail\SchoolVisitStaffReminderMail;
use App\Models\Cart;
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
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SchoolCockpitTest extends TestCase
{
    use RefreshDatabase;

    protected User $boss;

    protected Contact $teacher;

    protected CourseInstance $instance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();
        $this->boss = User::factory()->create();

        foreach (['Registration', 'CourseInstance', 'Course', 'Contact'] as $model) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete'] as $ability) {
                $this->boss->givePermissionTo(Permission::findOrCreate("{$ability}:{$model}"));
            }
        }

        $this->boss->givePermissionTo(Permission::findOrCreate('View:CompanySettingsPage'));
        $this->actingAs($this->boss);

        $this->teacher = Contact::factory()->teacher()->create(['name' => 'Frau Berg']);
        $course = Course::factory()->forSchoolClasses()->create(['name' => 'Roboter bauen', 'responsible_user_id' => $this->boss->id]);
        $this->instance = CourseInstance::factory()->for($course)->withDates(1, now()->addDays(30)->setTime(9, 0))->create();
    }

    protected function requestedBooking(): Registration
    {
        $cart = Cart::factory()->create();
        $service = app(SchoolBookingService::class);
        $registration = $service->hold($this->instance, $cart, $this->teacher);
        $service->checkout($cart, [$registration->id => ['class_name' => '4b', 'students' => 24, 'teacher_name' => 'Frau Berg', 'topic' => 'Sensoren | Licht']], $this->teacher);

        return $registration->fresh();
    }

    public function test_cockpit_pages_show_school_bookings(): void
    {
        $registration = $this->requestedBooking();
        CourseInstanceNote::factory()->for($this->instance)->create(['body' => 'Parkplatz hinter dem Haus']);

        $this->get('/cockpit/registrations?tab=schools')->assertOk()->assertSee('Klasse 4b');
        $this->get("/cockpit/registrations/{$registration->id}")
            ->assertOk()
            ->assertSee('Schulklasse')
            ->assertSee($this->teacher->school->name)
            ->assertSee('Parkplatz hinter dem Haus');
        $this->get("/cockpit/course-instances/{$this->instance->id}/edit")->assertOk()->assertSee('Erinnerungen und Fristen');
        $this->get("/cockpit/courses/{$this->instance->course_id}/edit")->assertOk();
        $this->get("/cockpit/contacts/{$this->teacher->school_id}/edit")->assertOk();
        Livewire::test(TeachersRelationManager::class, ['ownerRecord' => $this->teacher->school, 'pageClass' => EditContact::class])
            ->assertCanSeeTableRecords([$this->teacher]);
        $this->assertFalse(TeachersRelationManager::canViewForRecord($this->teacher, EditContact::class));
        $this->get('/cockpit/contacts?tab=teachers')->assertOk()->assertSee('Frau Berg');
    }

    public function test_course_saves_school_format_and_overrides(): void
    {
        $course = $this->instance->course;

        Livewire::test(EditCourse::class, ['record' => $course->id])
            ->fillForm([
                'school_format' => SchoolCourseFormat::Both->value,
                'teacher_reminder_days' => 7,
                'school_reminders_enabled' => '0',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $course->refresh();
        $this->assertSame(SchoolCourseFormat::Both, $course->school_format);
        $this->assertSame(7, $course->teacher_reminder_days);
        $this->assertFalse($course->school_reminders_enabled);
        $this->assertNull($course->staff_reminder_days);
    }

    public function test_notes_can_be_added_to_an_instance(): void
    {
        Livewire::test(NotesRelationManager::class, ['ownerRecord' => $this->instance, 'pageClass' => EditCourseInstance::class])
            ->callTableAction('create', data: ['body' => 'Schule bringt eigene Laptops mit.'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas(CourseInstanceNote::class, ['course_instance_id' => $this->instance->id, 'user_id' => $this->boss->id, 'body' => 'Schule bringt eigene Laptops mit.']);
    }

    public function test_company_settings_store_school_defaults(): void
    {
        $this->get('/cockpit/company-settings-page?tab=schulklassen::data::tab')->assertOk();

        Livewire::test(CompanySettingsPage::class)
            ->fillForm(['teacher_reminder_days' => 10, 'staff_reminder_days' => 20, 'school_reschedule_deadline_days' => 7, 'school_reminders_enabled' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = CompanySetting::current();
        $this->assertSame(10, $settings->teacher_reminder_days);
        $this->assertSame(7, $settings->school_reschedule_deadline_days);
        $this->assertFalse($settings->school_reminders_enabled);
    }

    public function test_all_school_mails_render(): void
    {
        $registration = $this->requestedBooking();
        $registration->load(['courseInstance.course', 'courseInstance.dates', 'courseInstance.internalNotes', 'school', 'contact']);

        $mails = [
            new SchoolLoginLinkMail(SchoolLoginLinkMail::REGISTRATION, 'https://example.com/login', $this->teacher->school),
            new SchoolLoginLinkMail(SchoolLoginLinkMail::INVITATION, 'https://example.com/login', $this->teacher, $this->teacher->school),
            new SchoolRequestReceivedMail(collect([$registration])),
            new SchoolRequestMail($registration, 'https://example.com/ja', 'https://example.com/nein'),
            new SchoolRequestDecidedMail($registration, approved: true, visitUrl: 'https://example.com/termin'),
            new SchoolRequestDecidedMail($registration, approved: false),
            new SchoolVisitReminderMail($registration, 'https://example.com/termin'),
            new SchoolVisitStaffReminderMail($registration),
            new SchoolBookingUpdateMail($registration, SchoolBookingUpdateMail::CANCELLED),
        ];

        foreach ($mails as $mail) {
            $html = $mail->render();
            $this->assertStringContainsString(CompanySetting::current()->homepageTitle(), $html.config('app.name'));
        }

        (new SchoolRequestMail($registration, 'https://example.com/ja', 'https://example.com/nein'))
            ->assertSeeInHtml('Klasse')
            ->assertSeeInHtml('Sensoren / Licht')
            ->assertSeeInHtml('https://example.com/ja');
    }
}
