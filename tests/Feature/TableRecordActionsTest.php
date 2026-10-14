<?php

namespace Tests\Feature;

use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Filament\Resources\Sections\Pages\ListSections;
use App\Filament\Resources\Sections\SectionResource;
use App\Models\Course;
use App\Models\Section;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Tables\Enums\RecordActionsPosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TableRecordActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();

        foreach (['Course', 'Section'] as $model) {
            foreach (['ViewAny', 'View', 'Update'] as $ability) {
                $user->givePermissionTo(Permission::findOrCreate("{$ability}:{$model}"));
            }
        }

        $this->actingAs($user);
    }

    public function test_clicking_a_row_opens_the_record_in_the_cockpit(): void
    {
        $course = Course::factory()->create();
        $section = Section::factory()->create();

        $courseTable = Livewire::test(ListCourses::class)->instance()->getTable();
        $sectionTable = Livewire::test(ListSections::class)->instance()->getTable();

        $this->assertSame(CourseResource::getUrl('edit', ['record' => $course]), $courseTable->getRecordUrl($course));
        $this->assertSame(SectionResource::getUrl('edit', ['record' => $section]), $sectionTable->getRecordUrl($section));
    }

    public function test_homepage_view_is_a_separate_record_action(): void
    {
        $course = Course::factory()->create();

        Livewire::test(ListCourses::class)
            ->assertActionExists(TestAction::make('viewOnHomepage')->table($course))
            ->assertActionHasUrl(TestAction::make('viewOnHomepage')->table($course), $course->url());
    }

    public function test_record_actions_are_icon_buttons_before_the_columns(): void
    {
        Course::factory()->create();

        $table = Livewire::test(ListCourses::class)->instance()->getTable();

        $this->assertSame(RecordActionsPosition::BeforeColumns, $table->getRecordActionsPosition());

        foreach ($table->getRecordActions() as $action) {
            $this->assertTrue($action->isIconButton(), "Action [{$action->getName()}] is not an icon button.");
        }
    }
}
