<?php

namespace Tests\Feature\Homepage;

use App\Models\CompanySetting;
use App\Models\Section;
use Database\Seeders\HomepageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_start_page_renders_without_sections(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Hier entsteht gerade etwas Neues.');
    }

    public function test_start_page_only_shows_root_sections_as_tiles(): void
    {
        $about = Section::factory()->create(['title' => 'Über uns', 'slug' => 'ueber-uns', 'teaser' => 'Wer wir sind']);
        Section::factory()->childOf($about)->create(['title' => 'Unser Team']);
        Section::factory()->hidden()->create(['title' => 'Versteckt']);
        Section::factory()->standalone()->create(['title' => 'Impressum', 'slug' => 'impressum']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Über uns')
            ->assertSee('Wer wir sind')
            ->assertSee('href="'.route('homepage.section', 'ueber-uns').'"', false)
            ->assertDontSee('Unser Team')
            ->assertDontSee('Versteckt')
            ->assertDontSee('href="'.route('homepage.section', 'impressum').'"', false);
    }

    public function test_tiles_are_rendered_in_sort_order(): void
    {
        Section::factory()->create(['title' => 'Zweiter Bereich', 'sort' => 2]);
        Section::factory()->create(['title' => 'Erster Bereich', 'sort' => 1]);

        $this->get('/')->assertSeeInOrder(['Erster Bereich', 'Zweiter Bereich']);
    }

    public function test_section_page_shows_content_and_next_level_as_tiles(): void
    {
        $workshops = Section::factory()->create([
            'title' => 'Workshops',
            'slug' => 'workshops',
            'content' => [['type' => 'text', 'data' => ['body' => '<p>Alles über Workshops</p>']]],
        ]);
        $robotik = Section::factory()->childOf($workshops)->create(['title' => 'Robotik', 'slug' => 'robotik']);
        Section::factory()->childOf($robotik)->create(['title' => 'Lego Mindstorms']);
        Section::factory()->childOf($workshops)->hidden()->create(['title' => 'Geheimer Workshop']);

        $this->get('/workshops')
            ->assertOk()
            ->assertSee('Alles über Workshops')
            ->assertSee('Robotik')
            ->assertSee('href="'.route('homepage.section', 'robotik').'"', false)
            ->assertDontSee('Lego Mindstorms')
            ->assertDontSee('Geheimer Workshop');
    }

    public function test_section_page_shows_breadcrumbs_back_to_the_start(): void
    {
        $workshops = Section::factory()->create(['title' => 'Workshops', 'slug' => 'workshops']);
        $robotik = Section::factory()->childOf($workshops)->create(['title' => 'Robotik', 'slug' => 'robotik']);
        Section::factory()->childOf($robotik)->create(['title' => 'Lego Mindstorms', 'slug' => 'lego']);

        $this->get('/lego')
            ->assertOk()
            ->assertSeeInOrder(['Start', 'Workshops', 'Robotik', 'Lego Mindstorms'])
            ->assertSee('href="'.route('homepage.section', 'robotik').'"', false);
    }

    public function test_sections_below_a_hidden_parent_are_not_reachable(): void
    {
        $hidden = Section::factory()->hidden()->create(['slug' => 'versteckt']);
        Section::factory()->childOf($hidden)->create(['slug' => 'darunter']);

        $this->get('/versteckt')->assertNotFound();
        $this->get('/darunter')->assertNotFound();
        $this->get('/gibt-es-nicht')->assertNotFound();
    }

    public function test_section_without_tile_has_its_own_page(): void
    {
        $parent = Section::factory()->create(['slug' => 'ueber-uns']);
        Section::factory()->childOf($parent)->standalone()->create([
            'title' => 'Datenschutz',
            'slug' => 'datenschutz',
            'content' => [['type' => 'text', 'data' => ['body' => '<p>Ihre Daten sind sicher</p>']]],
        ]);

        $this->get('/ueber-uns')->assertDontSee('Ihre Daten sind sicher');

        $this->get('/datenschutz')
            ->assertOk()
            ->assertSee('Ihre Daten sind sicher');
    }

    public function test_header_navigation_only_contains_flagged_root_sections(): void
    {
        Section::factory()->create(['title' => 'Im Menue', 'slug' => 'im-menue']);
        Section::factory()->create(['title' => 'Nicht im Menue', 'slug' => 'nicht-im-menue', 'show_in_navigation' => false]);

        $html = $this->get('/')->getContent();
        $nav = substr($html, strpos($html, 'Hauptnavigation'), 1000);

        $this->assertStringContainsString('Im Menue', $nav);
        $this->assertStringNotContainsString('Nicht im Menue', $nav);
    }

    public function test_all_content_blocks_render(): void
    {
        CompanySetting::current()->update(['contact_email' => 'info@example.com']);

        Section::factory()->create([
            'slug' => 'bloecke',
            'content' => [
                ['type' => 'text', 'data' => ['body' => '<p>Textblock</p>']],
                ['type' => 'image', 'data' => ['image' => 'homepage/bild.jpg', 'alt' => 'Ein Bild', 'caption' => 'Unterschrift']],
                ['type' => 'text_image', 'data' => ['body' => '<p>Mit Bild</p>', 'image' => 'homepage/b.jpg', 'alt' => 'B', 'image_position' => 'left']],
                ['type' => 'call_to_action', 'data' => ['heading' => 'Jetzt mitmachen', 'button_label' => 'Los', 'button_url' => '/kurse']],
                ['type' => 'contact', 'data' => ['intro' => '<p>Schreib uns</p>']],
                ['type' => 'unknown', 'data' => []],
            ],
        ]);

        $this->get('/bloecke')
            ->assertOk()
            ->assertSee('Textblock')
            ->assertSee('alt="Ein Bild"', false)
            ->assertSee('Unterschrift')
            ->assertSee('Mit Bild')
            ->assertSee('Jetzt mitmachen')
            ->assertSee('href="/kurse"', false)
            ->assertSee('info@example.com');
    }

    public function test_cockpit_lives_under_its_own_path(): void
    {
        $this->get('/cockpit')->assertRedirect('/cockpit/login');
        $this->get('/admin')->assertRedirect('/cockpit');
    }

    public function test_brand_color_and_title_come_from_company_settings(): void
    {
        CompanySetting::current()->update([
            'homepage_title' => 'Z-Lab',
            'homepage_tagline' => 'Forschen, bauen, staunen',
            'primary_color' => '#123456',
        ]);

        $this->get('/')
            ->assertSee('<title>Z-Lab</title>', false)
            ->assertSee('--brand: #123456', false)
            ->assertSee('Forschen, bauen, staunen');
    }

    public function test_footer_shows_configured_links_social_media_and_copyright(): void
    {
        $impressum = Section::factory()->standalone()->create(['title' => 'Impressum', 'slug' => 'impressum']);
        $hidden = Section::factory()->standalone()->hidden()->create(['slug' => 'alt']);

        CompanySetting::current()->update([
            'footer_text' => 'Z-LAB: Nachhaltig begeistern',
            'footer_links' => [
                ['label' => 'Impressum', 'section_id' => $impressum->id, 'url' => null],
                ['label' => 'Stiftung', 'section_id' => null, 'url' => 'https://www.auerbach-stiftung.de'],
                ['label' => 'Versteckter Link', 'section_id' => $hidden->id, 'url' => null],
                ['label' => 'Gefährlich', 'section_id' => null, 'url' => 'javascript:alert(1)'],
            ],
            'social_links' => [
                ['platform' => 'instagram', 'url' => 'https://www.instagram.com/z.lab.bruchsal'],
            ],
        ]);

        $this->get('/')
            ->assertSee('href="'.route('homepage.section', 'impressum').'"', false)
            ->assertSee('href="https://www.auerbach-stiftung.de"', false)
            ->assertDontSee('Versteckter Link')
            ->assertDontSee('javascript:alert', false)
            ->assertSee('href="https://www.instagram.com/z.lab.bruchsal"', false)
            ->assertSee('Instagram')
            ->assertSee('© '.now()->year.' Z-LAB: Nachhaltig begeistern');
    }

    public function test_seeded_homepage_renders(): void
    {
        $this->seed(HomepageSeeder::class);
        $this->seed(HomepageSeeder::class);

        $this->assertSame(1, Section::query()->where('slug', 'impressum')->count());

        $this->get('/')
            ->assertOk()
            ->assertSee('Z-LAB Bruchsal')
            ->assertSee('Für Kinder &amp; Eltern', false)
            ->assertSee('Für Schulen')
            ->assertSee('Werner-von-Siemens-Str. 2-6')
            ->assertSee('href="'.route('homepage.section', 'impressum').'"', false);

        $this->get('/fuer-schulen')->assertOk()->assertSee('Der Begeisterbus');
        $this->get('/impressum')->assertOk()->assertSee('Angaben gemäß § 5 DDG');
        $this->get('/datenschutz')->assertOk();
    }
}
