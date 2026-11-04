<?php

namespace Tests\Feature\Homepage;

use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Sections\Pages\EditSection;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\CourseInstance;
use App\Models\Interest;
use App\Models\Section;
use App\Models\User;
use App\Services\Homepage\HomepagePreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HomepagePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->user = User::factory()->create();

        foreach (['Course', 'Section'] as $model) {
            foreach (['ViewAny', 'View', 'Create', 'Update'] as $ability) {
                $this->user->givePermissionTo(Permission::findOrCreate("{$ability}:{$model}"));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function previewUrl(string $type, array $state, ?object $record = null, string $variant = 'seite', ?User $owner = null): string
    {
        $token = HomepagePreview::store($type, $state, $record, ($owner ?? $this->user)->id);

        return route('homepage.preview', ['token' => $token, 'variant' => $variant]);
    }

    public function test_guests_cannot_open_previews(): void
    {
        $url = $this->previewUrl(HomepagePreview::TYPE_COURSE, ['name' => 'Geheim']);

        $this->get($url)->assertForbidden();
    }

    public function test_previews_of_other_users_are_not_found(): void
    {
        $url = $this->previewUrl(HomepagePreview::TYPE_COURSE, ['name' => 'Geheim'], owner: User::factory()->create());

        $this->actingAs($this->user)->get($url)->assertNotFound();
    }

    public function test_unknown_tokens_are_not_found(): void
    {
        $this->actingAs($this->user)
            ->get(route('homepage.preview', ['token' => str_repeat('a', 40), 'variant' => 'karte']))
            ->assertNotFound();
    }

    public function test_user_without_update_permission_cannot_preview_course(): void
    {
        $course = Course::factory()->create();
        $user = User::factory()->create();
        $url = $this->previewUrl(HomepagePreview::TYPE_COURSE, ['name' => 'Geheim'], $course, owner: $user);

        $this->actingAs($user)->get($url)->assertForbidden();
    }

    public function test_course_card_preview_shows_unsaved_state_of_unpublished_course(): void
    {
        $course = Course::factory()->unpublished()->create(['name' => 'Alter Name']);
        $interest = Interest::factory()->create(['name' => 'Robotik']);

        $url = $this->previewUrl(HomepagePreview::TYPE_COURSE, [
            'name' => 'Neuer Name',
            'teaser' => 'Ungespeicherter Kurztext',
            'interests' => [$interest->id],
        ], $course, 'karte');

        $this->actingAs($this->user)
            ->get($url)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('Neuer Name')
            ->assertSee('Ungespeicherter Kurztext')
            ->assertSee('Robotik')
            ->assertDontSee('Alter Name');

        $this->assertSame('Alter Name', $course->fresh()->name);
    }

    public function test_course_detail_preview_disables_booking(): void
    {
        $course = Course::factory()->create();
        $instance = CourseInstance::factory()->for($course)->create();
        CourseDate::factory()->create(['course_instance_id' => $instance->id]);

        $url = $this->previewUrl(HomepagePreview::TYPE_COURSE, [
            'name' => 'Lötkurs',
            'description' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Aus dem Editor']]]]],
        ], $course);

        $this->actingAs($this->user)
            ->get($url)
            ->assertOk()
            ->assertSee('Lötkurs')
            ->assertSee('Aus dem Editor')
            ->assertSee('Die Anmeldung ist in der Vorschau deaktiviert.')
            ->assertDontSee(route('homepage.booking.start', $instance));
    }

    public function test_new_course_can_be_previewed(): void
    {
        $url = $this->previewUrl(HomepagePreview::TYPE_COURSE, ['name' => 'Ganz neuer Kurs', 'audience' => 'kids'], variant: 'karte');

        $this->actingAs($this->user)
            ->get($url)
            ->assertOk()
            ->assertSee('Ganz neuer Kurs');

        $this->assertDatabaseMissing('courses', ['name' => 'Ganz neuer Kurs']);
    }

    public function test_section_previews_show_unsaved_state_of_hidden_section(): void
    {
        $section = Section::factory()->hidden()->create(['title' => 'Alt']);
        Section::factory()->childOf($section)->create(['title' => 'Unterbereich']);

        $state = [
            'title' => 'Workshops',
            'teaser' => 'Neuer Kachel-Text',
            'tile_width' => 4,
            'tile_height' => 1,
            'content' => [
                'a1b2' => ['type' => 'text', 'data' => ['body' => '<p>Ungespeicherter Inhalt</p>']],
            ],
        ];

        $this->actingAs($this->user)
            ->get($this->previewUrl(HomepagePreview::TYPE_SECTION, $state, $section, 'karte'))
            ->assertOk()
            ->assertSee('Neuer Kachel-Text')
            ->assertSee('lg:col-span-4');

        $this->actingAs($this->user)
            ->get($this->previewUrl(HomepagePreview::TYPE_SECTION, $state, $section))
            ->assertOk()
            ->assertSee('Workshops')
            ->assertSee('Ungespeicherter Inhalt')
            ->assertSee('Unterbereich');

        $this->assertSame('Alt', $section->fresh()->title);
    }

    public function test_normalize_reduces_uploads_and_builder_items(): void
    {
        $normalized = HomepagePreview::normalize([
            'tile_image_path' => ['3f1c' => 'homepage/bild.jpg'],
            'card_image_path' => [],
            'interests' => [3, 5],
            'content' => [
                'uuid-1' => ['type' => 'image', 'data' => ['image' => ['9a' => 'homepage/x.png'], 'alt' => 'X']],
            ],
        ]);

        $this->assertSame('homepage/bild.jpg', $normalized['tile_image_path']);
        $this->assertNull($normalized['card_image_path']);
        $this->assertSame([3, 5], $normalized['interests']);
        $this->assertSame([['type' => 'image', 'data' => ['image' => 'homepage/x.png', 'alt' => 'X']]], $normalized['content']);
    }

    public function test_preview_action_opens_in_cockpit(): void
    {
        $this->actingAs($this->user);

        $course = Course::factory()->create();
        $section = Section::factory()->create();

        Livewire::test(EditCourse::class, ['record' => $course->getRouteKey()])
            ->mountAction('homepagePreview')
            ->assertActionMounted('homepagePreview')
            ->assertMountedActionModalSee(['Visitenkarte', 'Detailseite'])
            ->assertMountedActionModalSeeHtml('/vorschau/');

        Livewire::test(CreateCourse::class)
            ->fillForm(['name' => 'Entwurf'])
            ->mountAction('homepagePreview')
            ->assertActionMounted('homepagePreview');

        Livewire::test(EditSection::class, ['record' => $section->getRouteKey()])
            ->mountAction('homepagePreview')
            ->assertMountedActionModalSee(['Kachel', 'Seite']);
    }
}
