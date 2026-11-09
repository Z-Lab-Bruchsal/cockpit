<?php

namespace Tests\Feature\Courses;

use App\Enums\CourseAvailability;
use App\Enums\CourseInstanceStatus;
use App\Enums\CourseParticipation;
use App\Enums\CourseProminence;
use App\Filament\Resources\CourseInstances\Pages\CreateCourseInstance;
use App\Filament\Resources\CourseInstances\Pages\EditCourseInstance;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\Section;
use App\Models\User;
use App\Services\Homepage\HomepagePreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CourseOffersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function cockpitUser(): User
    {
        $user = User::factory()->create();

        foreach (['ViewAny', 'View', 'Create', 'Update'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:CourseInstance"));
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function dropIn(array $attributes = [], ?Course $course = null): CourseInstance
    {
        return CourseInstance::factory()
            ->for($course ?? Course::factory()->create())
            ->create([
                'participation' => CourseParticipation::DropIn,
                'schedule_text' => 'Mittwochs · 14:00–17:00',
                'schedule_note' => 'Außer in den Schulferien',
                ...$attributes,
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function offersPage(array $data = []): string
    {
        Section::factory()->create(['slug' => 'angebote', 'content' => [['type' => 'offers', 'data' => $data]]]);

        return $this->get('/angebote')->assertOk()->getContent();
    }

    public function test_offers_block_lists_published_upcoming_offers_only(): void
    {
        $this->dropIn(['title' => 'Offenes Tüfteln']);
        CourseInstance::factory()->withDates()->create(['title' => 'Löten für Anfänger']);
        CourseInstance::factory()->draft()->withDates()->create(['title' => 'Entwurf']);
        CourseInstance::factory()->withDates(1, now()->subWeek())->create(['title' => 'Vorbei']);
        CourseInstance::factory()->for(Course::factory()->unpublished())->withDates()->create(['title' => 'Kurs offline']);
        $this->dropIn(['title' => 'Abgelaufen', 'available_until' => today()->subDay()]);
        $this->dropIn(['title' => 'Später', 'available_from' => today()->addWeek()]);

        $html = $this->offersPage();

        $this->assertStringContainsString('Offenes Tüfteln', $html);
        $this->assertStringContainsString('Mittwochs · 14:00–17:00', $html);
        $this->assertStringContainsString('Außer in den Schulferien', $html);
        $this->assertStringContainsString('Löten für Anfänger', $html);

        foreach (['Entwurf', 'Vorbei', 'Kurs offline', 'Abgelaufen', 'Später'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $html);
        }
    }

    public function test_offers_can_be_limited_to_one_kind_and_show_filter_tabs(): void
    {
        $this->dropIn(['title' => 'Drone Soccer Training']);
        CourseInstance::factory()->withDates()->create(['title' => 'Murmelbahn-Labor']);

        $all = $this->offersPage(['show_filter_tabs' => true]);

        $this->assertStringContainsString('Alle Angebote', $all);
        $this->assertStringContainsString('Einfach vorbeikommen', $all);
        $this->assertStringContainsString('Kurse &amp; Workshops', $all);

        Section::query()->delete();
        $onlyDropIn = $this->offersPage(['participations' => [CourseParticipation::DropIn->value]]);

        $this->assertStringContainsString('Drone Soccer Training', $onlyDropIn);
        $this->assertStringNotContainsString('Murmelbahn-Labor', $onlyDropIn);
        $this->assertStringNotContainsString('Alle Angebote', $onlyDropIn);
    }

    public function test_featured_offers_come_first_and_are_large(): void
    {
        CourseInstance::factory()->withDates(1, now()->addDay())->create(['title' => 'Bald']);
        CourseInstance::factory()->withDates(1, now()->addMonth())->create(['title' => 'Groß', 'prominence' => CourseProminence::Featured]);

        $html = $this->offersPage();

        $this->assertMatchesRegularExpression('/sm:col-span-2 lg:col-span-6[^>]*>.*?Groß/s', $html);
        $this->assertLessThan(strpos($html, '>Bald<'), strpos($html, '>Groß<'));
    }

    public function test_card_uses_own_content_and_falls_back_to_the_course(): void
    {
        $course = Course::factory()->create([
            'name' => 'Löten',
            'teaser' => 'Kurstext',
            'card_image_path' => 'courses/kurs.jpg',
            'card_image_alt' => 'Kursbild',
            'description' => '<p>Kursbeschreibung</p>',
        ]);
        $plain = CourseInstance::factory()->for($course)->withDates()->create();
        $own = CourseInstance::factory()->for($course)->withDates()->create([
            'title' => 'Löten im Herbst',
            'teaser' => 'Eigener Text',
            'card_image_path' => 'courses/eigen.jpg',
            'card_image_alt' => 'Eigenes Bild',
            'description' => '<p>Eigene Beschreibung</p>',
            'card_label' => 'Löten lernen. Selber machen.',
        ]);

        $this->assertSame('Löten', $plain->displayTitle());
        $this->assertSame('Kurstext', $plain->teaserText());
        $this->assertSame('Kursbild', $plain->cardImage()['alt']);
        $this->assertSame('<p>Kursbeschreibung</p>', $plain->descriptionHtml());
        $this->assertNull($plain->cardLabel());

        $this->assertSame('Eigener Text', $own->teaserText());
        $this->assertSame('Eigenes Bild', $own->cardImage()['alt']);
        $this->assertSame('Eigenes Bild', $own->detailImage()['alt']);
        $this->assertSame('<p>Eigene Beschreibung</p>', $own->descriptionHtml());
        $this->assertSame(['main' => 'Löten lernen. Selber machen.', 'sub' => null], $own->cardLabel());

        $this->assertSame(
            ['main' => 'Offen', 'sub' => 'Ohne Anmeldung'],
            $this->dropIn(['card_label' => 'Offen'])->cardLabel(),
        );
    }

    public function test_instance_detail_page_shows_dates_and_booking(): void
    {
        $instance = CourseInstance::factory()
            ->for(Course::factory()->create(['name' => 'Löten für Anfänger', 'slug' => 'loeten', 'min_age' => 10, 'max_age' => null]))
            ->withDates(2)
            ->create(['card_label' => 'Löten lernen. Selber machen.']);

        $this->get("/kurse/loeten/{$instance->id}")
            ->assertOk()
            ->assertSee('Löten für Anfänger')
            ->assertSee('Beginnt am')
            ->assertSee('ab 10 Jahren')
            ->assertSee('Termine')
            ->assertSee('Löten lernen. Selber machen.')
            ->assertSee('id="anmeldung"', false)
            ->assertSee(route('homepage.booking.start', $instance), false);
    }

    public function test_instance_detail_page_is_not_found_for_drafts_or_wrong_course(): void
    {
        $draft = CourseInstance::factory()->draft()->for(Course::factory()->create(['slug' => 'kurs']))->withDates()->create();
        $other = CourseInstance::factory()->for(Course::factory()->create(['slug' => 'anderer']))->withDates()->create();

        $this->get("/kurse/kurs/{$draft->id}")->assertNotFound();
        $this->get("/kurse/kurs/{$other->id}")->assertNotFound();
    }

    public function test_drop_in_offers_cannot_be_booked(): void
    {
        $instance = $this->dropIn([], Course::factory()->create(['slug' => 'tuefteln']));

        $this->assertSame(CourseAvailability::DropIn, $instance->availability());
        $this->assertFalse($instance->availability()->acceptsRegistrations());

        $this->get("/kurse/tuefteln/{$instance->id}")
            ->assertOk()
            ->assertSee('Mittwochs · 14:00–17:00')
            ->assertSee('einfach vorbeikommen')
            ->assertDontSee(route('homepage.booking.start', $instance), false);

        $this->post(route('homepage.booking.start', $instance), ['seats' => 1]);

        $this->assertSame(0, $instance->registrations()->count());
    }

    public function test_drop_in_offer_is_shown_on_the_course_page(): void
    {
        $course = Course::factory()->create(['slug' => 'tuefteln']);
        $this->dropIn(['title' => 'Offenes Tüfteln'], $course);

        $this->get('/kurse/tuefteln')
            ->assertOk()
            ->assertSee('Offenes Tüfteln')
            ->assertSee('Mittwochs · 14:00–17:00')
            ->assertSee('Keine Anmeldung nötig');
    }

    public function test_drop_in_offer_can_be_created_without_dates_and_seats(): void
    {
        $this->actingAs($this->cockpitUser());
        $course = Course::factory()->create(['default_participation' => CourseParticipation::DropIn]);

        Livewire::test(CreateCourseInstance::class)
            ->fillForm(['course_id' => $course->id])
            ->assertSchemaStateSet(['participation' => CourseParticipation::DropIn])
            ->fillForm([
                'status' => CourseInstanceStatus::Published->value,
                'prominence' => CourseProminence::Featured->value,
                'schedule_text' => 'Montags · 16:30–19:00',
                'available_from' => '2026-09-01',
                'card_label' => 'Drone Soccer Training',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $instance = CourseInstance::query()->where('course_id', $course->id)->sole();

        $this->assertTrue($instance->isDropIn());
        $this->assertTrue($instance->isFeatured());
        $this->assertSame(0, $instance->capacity);
        $this->assertSame(0, $instance->dates()->count());
        $this->assertSame('Drone Soccer Training', $instance->card_label);
    }

    public function test_drop_in_offer_needs_schedule_text(): void
    {
        $this->actingAs($this->cockpitUser());

        Livewire::test(CreateCourseInstance::class)
            ->fillForm([
                'course_id' => Course::factory()->create()->id,
                'participation' => CourseParticipation::DropIn->value,
                'schedule_text' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['schedule_text' => 'required']);
    }

    public function test_instance_previews_show_unsaved_state(): void
    {
        $user = $this->cockpitUser();
        $instance = CourseInstance::factory()->withDates()->create(['title' => 'Alt']);
        $state = [
            'course_id' => $instance->course_id,
            'title' => 'Ungespeichert',
            'participation' => CourseParticipation::Registration->value,
            'prominence' => CourseProminence::Featured->value,
            'card_label' => 'Neues Etikett',
            'dates' => ['x1' => ['starts_at' => '2026-12-01 15:00:00', 'ends_at' => '2026-12-01 17:00:00']],
        ];

        $this->actingAs($user);

        foreach (['karte', 'seite'] as $variant) {
            $token = HomepagePreview::store(HomepagePreview::TYPE_COURSE_INSTANCE, $state, $instance, $user->id);

            $this->get(route('homepage.preview', ['token' => $token, 'variant' => $variant]))
                ->assertOk()
                ->assertSee('Ungespeichert')
                ->assertSee('Neues Etikett');
        }

        $this->assertSame('Alt', $instance->fresh()->title);

        Livewire::test(EditCourseInstance::class, ['record' => $instance->getRouteKey()])
            ->mountAction('homepagePreview')
            ->assertMountedActionModalSee(['Karte', 'Detailseite']);
    }
}
