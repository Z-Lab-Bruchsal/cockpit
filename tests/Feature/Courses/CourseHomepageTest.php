<?php

namespace Tests\Feature\Courses;

use App\Enums\CourseCardStyle;
use App\Enums\CourseDetailLayout;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\CourseTemplate;
use App\Models\Interest;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseHomepageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_course_list_shows_only_published_courses_with_next_date(): void
    {
        $published = Course::factory()->create(['name' => 'Lasercutter']);
        CourseInstance::factory()->for($published)->withDates(1, now()->addDays(3)->setTime(16, 0))->create();
        Course::factory()->unpublished()->create(['name' => 'Geheimkurs']);

        $this->get('/kurse')
            ->assertOk()
            ->assertSee('Lasercutter')
            ->assertSee('Nächster Termin')
            ->assertSee(now()->addDays(3)->format('d.m.Y'))
            ->assertSee('href="'.route('homepage.courses.show', $published->slug).'"', false)
            ->assertDontSee('Geheimkurs');
    }

    public function test_courses_with_dates_come_before_courses_without(): void
    {
        Course::factory()->create(['name' => 'Ohne Termin']);
        $later = Course::factory()->create(['name' => 'Spaeter']);
        CourseInstance::factory()->for($later)->withDates(1, now()->addWeeks(3))->create();
        $sooner = Course::factory()->create(['name' => 'Bald']);
        CourseInstance::factory()->for($sooner)->withDates(1, now()->addDays(2))->create();

        $this->get('/kurse')->assertSeeInOrder(['Bald', 'Spaeter', 'Ohne Termin']);
    }

    public function test_course_list_can_be_filtered_by_interest_age_and_audience(): void
    {
        $robotics = Interest::factory()->create(['slug' => 'robotik']);
        Course::factory()->create(['name' => 'Roboterkurs', 'min_age' => 8, 'max_age' => 12])->interests()->attach($robotics);
        Course::factory()->create(['name' => 'Nähkurs', 'min_age' => 12, 'max_age' => 16]);
        Course::factory()->forSchoolClasses()->create(['name' => 'Klassenworkshop', 'min_age' => null, 'max_age' => null]);

        $this->get('/kurse?interesse=robotik')->assertSee('Roboterkurs')->assertDontSee('Nähkurs');
        $this->get('/kurse?alter=14')->assertSee('Nähkurs')->assertDontSee('Roboterkurs');
        $this->get('/kurse?zielgruppe=school_class')->assertSee('Klassenworkshop')->assertDontSee('Roboterkurs');
        $this->get('/kurse?alter=abc')->assertSessionHasErrors('alter');
    }

    public function test_detail_page_shows_upcoming_instances_with_all_dates(): void
    {
        $course = Course::factory()->create(['name' => 'Löten', 'description' => '<p>Wir löten eine Schaltung.</p>']);
        $instance = CourseInstance::factory()->for($course)->withDates(2, now()->addWeek()->setTime(15, 0))->create(['capacity' => 8]);
        CourseInstance::factory()->for($course)->draft()->withDates()->create(['title' => 'Entwurfstermin']);
        CourseInstance::factory()->for($course)->withDates(1, now()->subMonth())->create(['title' => 'Vergangener Termin']);

        $this->get('/kurse/'.$course->slug)
            ->assertOk()
            ->assertSee('Wir löten eine Schaltung.')
            ->assertSee('id="durchfuehrung-'.$instance->id.'"', false)
            ->assertSee(now()->addWeek()->format('d.m.Y'))
            ->assertSee(now()->addWeeks(2)->format('d.m.Y'))
            ->assertSee('8 von 8 Plätzen frei')
            ->assertSee('Plätze frei')
            ->assertDontSee('Entwurfstermin')
            ->assertDontSee('Vergangener Termin');
    }

    public function test_unpublished_course_has_no_detail_page(): void
    {
        $course = Course::factory()->unpublished()->create();

        $this->get('/kurse/'.$course->slug)->assertNotFound();
        $this->get('/kurse/gibt-es-nicht')->assertNotFound();
    }

    public function test_template_controls_card_and_detail_presentation(): void
    {
        $template = CourseTemplate::factory()->create([
            'card_style' => CourseCardStyle::Compact,
            'detail_layout' => CourseDetailLayout::Minimal,
            'accent_color' => '#0ea5e9',
            'show_free_seats' => false,
            'extra_content' => [['type' => 'text', 'data' => ['body' => '<p>Bitte Trinkflasche mitbringen</p>']]],
        ]);
        $course = Course::factory()->create(['course_template_id' => $template->id, 'card_image_path' => 'courses/karte.jpg', 'image_path' => 'courses/gross.jpg']);
        CourseInstance::factory()->for($course)->withDates()->create();

        $this->get('/kurse')
            ->assertSee('--brand: #0ea5e9', false)
            ->assertDontSee('courses/karte.jpg');

        $this->get('/kurse/'.$course->slug)
            ->assertSee('Bitte Trinkflasche mitbringen')
            ->assertDontSee('courses/gross.jpg')
            ->assertDontSee('Plätzen frei');
    }

    public function test_courses_without_template_use_the_default_template(): void
    {
        CourseTemplate::factory()->default()->create([
            'card_style' => CourseCardStyle::ImageTop,
            'default_card_image_path' => 'courses/ersatz.jpg',
        ]);
        Course::factory()->create();

        $this->get('/kurse')->assertSee('courses/ersatz.jpg');
    }

    public function test_only_one_template_can_be_the_default(): void
    {
        $first = CourseTemplate::factory()->default()->create();
        $second = CourseTemplate::factory()->default()->create();

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertTrue(CourseTemplate::fallback()->is($second));
    }

    public function test_calendar_page_renders(): void
    {
        $this->get('/kalender')
            ->assertOk()
            ->assertSee('data-course-calendar', false)
            ->assertSee(route('homepage.calendar.events'), false);
    }

    public function test_calendar_events_contain_published_dates_in_range(): void
    {
        $course = Course::factory()->create(['name' => 'Datenanalyse']);
        $instance = CourseInstance::factory()->for($course)->withDates(2, now()->addDays(2)->setTime(16, 0))->create();
        CourseInstance::factory()->for($course)->draft()->withDates(1, now()->addDays(3))->create(['title' => 'Entwurf']);
        CourseInstance::factory()->for(Course::factory()->unpublished())->withDates(1, now()->addDays(3))->create(['title' => 'Unveröffentlicht']);
        CourseInstance::factory()->for($course)->withDates(1, now()->addMonths(6))->create(['title' => 'Viel später']);

        $response = $this->getJson('/kalender/events?'.http_build_query([
            'start' => now()->startOfDay()->toIso8601String(),
            'end' => now()->addMonth()->toIso8601String(),
        ]));

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.title', 'Datenanalyse')
            ->assertJsonPath('0.url', route('homepage.courses.show', $course->slug).'#durchfuehrung-'.$instance->id)
            ->assertJsonPath('0.extendedProps.availability', 'Plätze frei')
            ->assertJsonPath('0.backgroundColor', '#16a34a');
    }

    public function test_calendar_events_can_be_filtered_by_audience_and_validate_input(): void
    {
        CourseInstance::factory()->for(Course::factory()->forSchoolClasses())->withDates(1, now()->addDays(2))->create();
        CourseInstance::factory()->withDates(1, now()->addDays(2))->create();

        $range = ['start' => now()->toIso8601String(), 'end' => now()->addMonth()->toIso8601String()];

        $this->getJson('/kalender/events?'.http_build_query($range + ['zielgruppe' => 'school_class']))->assertJsonCount(1);
        $this->getJson('/kalender/events?'.http_build_query($range))->assertJsonCount(2);
        $this->getJson('/kalender/events?start=gestern')->assertUnprocessable();
    }

    public function test_course_blocks_render_inside_sections(): void
    {
        $robotics = Interest::factory()->create();
        Course::factory()->create(['name' => 'Roboter bauen'])->interests()->attach($robotics);
        Course::factory()->create(['name' => 'Nähen']);

        Section::factory()->create([
            'slug' => 'kurse-am-nachmittag',
            'content' => [
                ['type' => 'course_list', 'data' => ['interest_ids' => [(string) $robotics->id], 'audience' => null, 'limit' => 3, 'show_all_link' => true]],
                ['type' => 'course_calendar', 'data' => ['audience' => 'kids']],
            ],
        ]);

        $this->get('/kurse-am-nachmittag')
            ->assertOk()
            ->assertSee('Roboter bauen')
            ->assertDontSee('Nähen')
            ->assertSee('Alle Kurse ansehen')
            ->assertSee('data-course-calendar', false)
            ->assertSee('zielgruppe=kids', false);
    }

    public function test_header_links_to_courses_and_calendar(): void
    {
        $this->get('/')
            ->assertSee('href="'.route('homepage.courses.index').'"', false)
            ->assertSee('href="'.route('homepage.calendar').'"', false);
    }
}
