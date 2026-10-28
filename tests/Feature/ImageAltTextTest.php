<?php

namespace Tests\Feature;

use App\Enums\CourseDetailLayout;
use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Sections\Pages\CreateSection;
use App\Models\Course;
use App\Models\CourseTemplate;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ImageAltTextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    protected function actingAsEditor(): void
    {
        $user = User::factory()->create();

        foreach (['Course', 'Section'] as $model) {
            foreach (['ViewAny', 'View', 'Create', 'Update'] as $ability) {
                $user->givePermissionTo(Permission::findOrCreate("{$ability}:{$model}"));
            }
        }

        $this->actingAs($user);
    }

    public function test_course_image_requires_alt_text(): void
    {
        $this->actingAsEditor();

        Livewire::test(CreateCourse::class)
            ->fillForm([
                'name' => 'Lasercutter',
                'audience' => 'kids',
                'duration' => 90,
                'default_capacity' => 10,
                'card_image_path' => UploadedFile::fake()->image('karte.jpg'),
                'card_image_alt' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['card_image_alt' => 'required_with']);

        Livewire::test(CreateCourse::class)
            ->fillForm([
                'name' => 'Lasercutter',
                'audience' => 'kids',
                'duration' => 90,
                'default_capacity' => 10,
                'card_image_path' => UploadedFile::fake()->image('karte.jpg'),
                'card_image_alt' => 'Lasercutter graviert ein Holzbrett',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('Lasercutter graviert ein Holzbrett', Course::query()->firstOrFail()->card_image_alt);
    }

    public function test_alt_text_is_optional_without_image(): void
    {
        $this->actingAsEditor();

        Livewire::test(CreateSection::class)
            ->fillForm(['title' => 'Ohne Bild', 'slug' => 'ohne-bild'])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_section_tile_image_requires_alt_text(): void
    {
        $this->actingAsEditor();

        Livewire::test(CreateSection::class)
            ->fillForm([
                'title' => 'Workshops',
                'slug' => 'workshops',
                'tile_image_path' => UploadedFile::fake()->image('kachel.jpg'),
            ])
            ->call('create')
            ->assertHasFormErrors(['tile_image_alt' => 'required_with']);
    }

    public function test_image_blocks_require_alt_text(): void
    {
        $this->actingAsEditor();

        Livewire::test(CreateSection::class)
            ->fillForm([
                'title' => 'Galerie',
                'slug' => 'galerie',
                'content' => [
                    ['type' => 'image', 'data' => ['image' => ['homepage/bild.jpg'], 'alt' => '']],
                ],
            ])
            ->call('create')
            ->assertHasErrors();

        $this->assertSame(0, Section::query()->count());

        Livewire::test(CreateSection::class)
            ->fillForm([
                'title' => 'Galerie',
                'slug' => 'galerie',
                'content' => [
                    ['type' => 'image', 'data' => ['image' => [UploadedFile::fake()->image('bild.jpg')], 'alt' => 'Werkstatt von innen']],
                ],
            ])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertSame(1, Section::query()->count());
    }

    public function test_alt_texts_are_rendered_on_the_homepage(): void
    {
        Section::factory()->create(['tile_image_path' => 'homepage/kachel.jpg', 'tile_image_alt' => 'Kinder am 3D-Drucker']);

        $template = CourseTemplate::factory()->create(['detail_layout' => CourseDetailLayout::HeroImage]);
        $course = Course::factory()->create([
            'course_template_id' => $template->id,
            'card_image_path' => 'courses/karte.jpg',
            'card_image_alt' => 'Lötkolben auf einer Platine',
            'image_path' => 'courses/gross.jpg',
            'image_alt' => 'Gruppe beim Löten',
        ]);

        $this->get('/')->assertSee('alt="Kinder am 3D-Drucker"', false);
        $this->get('/kurse')->assertSee('alt="Lötkolben auf einer Platine"', false);
        $this->get('/kurse/'.$course->slug)->assertSee('alt="Gruppe beim Löten"', false);
    }

    public function test_card_falls_back_to_large_image_with_its_alt_text(): void
    {
        Course::factory()->create(['image_path' => 'courses/gross.jpg', 'image_alt' => 'Gruppe beim Löten']);

        $this->get('/kurse')
            ->assertSee('courses/gross.jpg')
            ->assertSee('alt="Gruppe beim Löten"', false);
    }
}
