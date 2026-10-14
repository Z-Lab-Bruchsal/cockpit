<?php

namespace Tests\Feature\Courses;

use App\Enums\CourseInstanceStatus;
use App\Filament\Resources\CourseInstances\Pages\CreateCourseInstance;
use App\Filament\Resources\CourseInstances\Pages\EditCourseInstance;
use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\RelationManagers\InstancesRelationManager;
use App\Filament\Resources\Interests\Pages\CreateInterest;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\CourseTemplate;
use App\Models\Interest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CourseCockpitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $user = User::factory()->create();

        foreach (['Course', 'CourseInstance', 'CourseTemplate', 'Interest'] as $model) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Reorder', 'Replicate'] as $ability) {
                $user->givePermissionTo(Permission::findOrCreate("{$ability}:{$model}"));
            }
        }

        $this->actingAs($user);
    }

    public function test_pages_render(): void
    {
        $course = Course::factory()->create();
        $instance = CourseInstance::factory()->for($course)->withDates()->create();
        $template = CourseTemplate::factory()->create();
        $interest = Interest::factory()->create();

        foreach ([
            '/cockpit/courses', '/cockpit/courses/create', "/cockpit/courses/{$course->id}/edit",
            '/cockpit/course-instances', '/cockpit/course-instances/create', "/cockpit/course-instances/{$instance->id}/edit",
            '/cockpit/course-templates', '/cockpit/course-templates/create', "/cockpit/course-templates/{$template->id}/edit",
            '/cockpit/interests', '/cockpit/interests/create', "/cockpit/interests/{$interest->id}/edit",
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_user_without_permission_cannot_manage_instances(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/cockpit/course-instances')->assertForbidden();
        $this->get('/cockpit/interests')->assertForbidden();
    }

    public function test_can_create_course_with_interests_and_generated_slug(): void
    {
        $interest = Interest::factory()->create();

        Livewire::test(CreateCourse::class)
            ->fillForm([
                'name' => 'Löten für Anfänger',
                'teaser' => 'Erste Schaltung',
                'audience' => 'kids',
                'interests' => [$interest->id],
                'duration' => 120,
                'default_capacity' => 8,
                'min_age' => 10,
                'max_age' => 14,
                'is_published' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $course = Course::query()->where('name', 'Löten für Anfänger')->firstOrFail();
        $this->assertSame('loeten-fuer-anfaenger', $course->slug);
        $this->assertTrue($course->interests->contains($interest));
    }

    public function test_max_age_must_not_be_below_min_age(): void
    {
        Livewire::test(CreateCourse::class)
            ->fillForm(['name' => 'Kurs', 'audience' => 'kids', 'duration' => 60, 'default_capacity' => 5, 'min_age' => 12, 'max_age' => 8])
            ->call('create')
            ->assertHasFormErrors(['max_age']);
    }

    public function test_can_create_instance_with_multiple_dates(): void
    {
        $course = Course::factory()->create(['default_capacity' => 12]);

        Livewire::test(CreateCourseInstance::class)
            ->fillForm([
                'course_id' => $course->id,
                'status' => CourseInstanceStatus::Published->value,
                'capacity' => 12,
                'waitlist_enabled' => true,
                'dates' => [
                    ['starts_at' => '2026-11-04 16:00:00', 'ends_at' => '2026-11-04 17:30:00', 'location' => null],
                    ['starts_at' => '2026-11-07 10:00:00', 'ends_at' => '2026-11-07 13:00:00', 'location' => 'Turnhalle'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $instance = CourseInstance::query()->where('course_id', $course->id)->firstOrFail();
        $this->assertSame(2, $instance->dates()->count());
        $this->assertSame('Turnhalle', $instance->dates->last()->location);
        $this->assertSame('10:00', $instance->dates->last()->starts_at->format('H:i'));
    }

    public function test_date_must_end_after_it_starts(): void
    {
        $course = Course::factory()->create();

        Livewire::test(CreateCourseInstance::class)
            ->fillForm([
                'course_id' => $course->id,
                'status' => CourseInstanceStatus::Draft->value,
                'capacity' => 10,
                'dates' => [
                    ['starts_at' => '2026-11-04 16:00:00', 'ends_at' => '2026-11-04 15:00:00'],
                ],
            ])
            ->call('create')
            ->assertHasErrors();

        $this->assertSame(0, CourseInstance::query()->count());
    }

    public function test_choosing_a_course_prefills_capacity_and_waitlist(): void
    {
        $course = Course::factory()->create(['default_capacity' => 4, 'default_waitlist_enabled' => false]);

        Livewire::test(CreateCourseInstance::class)
            ->fillForm(['course_id' => $course->id])
            ->assertSchemaStateSet([
                'capacity' => 4,
                'waitlist_enabled' => false,
            ]);
    }

    public function test_can_edit_instance_dates(): void
    {
        $instance = CourseInstance::factory()->withDates(2)->create();

        Livewire::test(EditCourseInstance::class, ['record' => $instance->id])
            ->fillForm(['title' => 'Herbstferien'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Herbstferien', $instance->fresh()->title);
        $this->assertSame(2, $instance->dates()->count());
    }

    public function test_course_edit_page_lists_its_instances(): void
    {
        $course = Course::factory()->create();
        $instances = CourseInstance::factory()->for($course)->withDates()->count(2)->create();

        Livewire::test(InstancesRelationManager::class, ['ownerRecord' => $course, 'pageClass' => EditCourse::class])
            ->assertCanSeeTableRecords($instances);
    }

    public function test_can_create_interest(): void
    {
        Livewire::test(CreateInterest::class)
            ->fillForm(['name' => 'Robotik', 'slug' => 'robotik', 'color' => '#7c3aed', 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Interest::class, ['slug' => 'robotik', 'color' => '#7c3aed']);
    }
}
