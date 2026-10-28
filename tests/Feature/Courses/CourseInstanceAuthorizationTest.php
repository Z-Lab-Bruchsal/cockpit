<?php

namespace Tests\Feature\Courses;

use App\Enums\EnrollmentStatus;
use App\Filament\Resources\CourseInstances\Pages\EditCourseInstance;
use App\Filament\Resources\CourseInstances\RelationManagers\AttendanceRelationManager;
use App\Filament\Resources\CourseInstances\RelationManagers\ParticipantsRelationManager;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Filament\Resources\Interests\Pages\ListInterests;
use App\Filament\Resources\Sections\Pages\ListSections;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\Enrollment;
use App\Models\Interest;
use App\Models\Registration;
use App\Models\Section;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Users who may only look at course instances must not change participants, attendance or published flags.
 */
class CourseInstanceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected CourseInstance $instance;

    protected Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();

        $this->instance = CourseInstance::factory()->withDates()->create();
        $registration = Registration::factory()->create(['course_instance_id' => $this->instance->id]);
        $this->enrollment = Enrollment::factory()->create(['registration_id' => $registration->id]);
    }

    /**
     * @param  list<string>  $models
     * @param  list<string>  $abilities
     */
    protected function actingAsUserWith(array $models, array $abilities): User
    {
        $user = User::factory()->create();

        foreach ($models as $model) {
            foreach ($abilities as $ability) {
                $user->givePermissionTo(Permission::findOrCreate("{$ability}:{$model}"));
            }
        }

        $this->actingAs($user);

        return $user;
    }

    protected function participants(): mixed
    {
        return Livewire::test(ParticipantsRelationManager::class, ['ownerRecord' => $this->instance, 'pageClass' => EditCourseInstance::class]);
    }

    protected function attendance(): mixed
    {
        return Livewire::test(AttendanceRelationManager::class, ['ownerRecord' => $this->instance, 'pageClass' => EditCourseInstance::class]);
    }

    public function test_viewer_cannot_change_participants(): void
    {
        $this->actingAsUserWith(['CourseInstance', 'Registration'], ['ViewAny', 'View']);

        $this->participants()
            ->assertCanSeeTableRecords([$this->enrollment])
            ->assertActionHidden(TestAction::make('registerKid')->table())
            ->assertActionHidden(TestAction::make('cancelEnrollment')->table($this->enrollment));

        $this->assertSame(EnrollmentStatus::Confirmed, $this->enrollment->fresh()->status);
    }

    public function test_viewer_cannot_take_kids_from_the_waitlist(): void
    {
        $this->enrollment->update(['status' => EnrollmentStatus::Waitlisted, 'waitlist_position' => 1]);
        $this->actingAsUserWith(['CourseInstance', 'Registration'], ['ViewAny', 'View']);

        $this->participants()
            ->set('tableFilters.status.values', [EnrollmentStatus::Waitlisted->value])
            ->assertActionHidden(TestAction::make('confirmFromWaitlist')->table($this->enrollment));

        $this->assertSame(EnrollmentStatus::Waitlisted, $this->enrollment->fresh()->status);
    }

    public function test_editor_can_change_participants(): void
    {
        $this->actingAsUserWith(['CourseInstance', 'Registration'], ['ViewAny', 'View', 'Update']);

        $this->participants()
            ->assertActionVisible(TestAction::make('registerKid')->table())
            ->callAction(TestAction::make('cancelEnrollment')->table($this->enrollment), ['notify' => false]);

        $this->assertSame(EnrollmentStatus::Cancelled, $this->enrollment->fresh()->status);
    }

    public function test_viewer_cannot_record_attendance(): void
    {
        $this->actingAsUserWith(['CourseInstance'], ['ViewAny', 'View']);
        $date = $this->instance->dates()->first();

        $this->attendance()
            ->assertActionHidden(TestAction::make('markAllPresent')->table())
            ->call('updateTableColumnState', 'attendance_'.$date->id, (string) $this->enrollment->id, true);

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_editor_can_record_attendance(): void
    {
        $this->actingAsUserWith(['CourseInstance'], ['ViewAny', 'View', 'Update']);
        $date = $this->instance->dates()->first();

        $this->attendance()
            ->call('updateTableColumnState', 'attendance_'.$date->id, (string) $this->enrollment->id, true);

        $this->assertTrue(Attendance::query()->sole()->attended);
    }

    public function test_viewer_cannot_toggle_published_flags(): void
    {
        $course = Course::factory()->create(['is_published' => false]);
        $section = Section::factory()->hidden()->create();
        $interest = Interest::factory()->create(['is_active' => false]);
        $this->actingAsUserWith(['Course', 'Section', 'Interest'], ['ViewAny', 'View']);

        Livewire::test(ListCourses::class)->call('updateTableColumnState', 'is_published', (string) $course->id, true);
        Livewire::test(ListSections::class)->call('updateTableColumnState', 'is_visible', (string) $section->id, true);
        Livewire::test(ListInterests::class)->call('updateTableColumnState', 'is_active', (string) $interest->id, true);

        $this->assertFalse($course->fresh()->is_published);
        $this->assertFalse($section->fresh()->is_visible);
        $this->assertFalse($interest->fresh()->is_active);
    }

    public function test_editor_can_toggle_published_flags(): void
    {
        $course = Course::factory()->create(['is_published' => false]);
        $this->actingAsUserWith(['Course'], ['ViewAny', 'View', 'Update']);

        Livewire::test(ListCourses::class)->call('updateTableColumnState', 'is_published', (string) $course->id, true);

        $this->assertTrue($course->fresh()->is_published);
    }
}
