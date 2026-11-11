<?php

namespace Tests\Feature\Homepage;

use App\Enums\TileStyle;
use App\Enums\TileType;
use App\Filament\Resources\Sections\Pages\EditSection;
use App\Filament\Resources\Sections\RelationManagers\TilesRelationManager;
use App\Models\Course;
use App\Models\Interest;
use App\Models\Section;
use App\Models\Tile;
use App\Models\User;
use App\Services\Homepage\TileGrid;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TileGridTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function page(): Section
    {
        return Section::factory()->create(['slug' => 'seite']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function contentTile(Section $page, array $attributes = []): Tile
    {
        return Tile::factory()->for($page)->create(['text' => null, ...$attributes]);
    }

    public function test_tiles_link_to_all_target_types(): void
    {
        $about = Section::factory()->create(['title' => 'Über uns', 'slug' => 'ueber-uns']);
        $page = $this->page();

        $this->contentTile($page, ['heading' => 'Bereich', 'link_type' => 'section', 'link_section_id' => $about->id, 'link_anchor' => 'team']);
        $this->contentTile($page, ['heading' => 'Kurse', 'link_type' => 'courses']);
        $this->contentTile($page, ['heading' => 'Kalender', 'link_type' => 'calendar']);
        $this->contentTile($page, ['heading' => 'Neuigkeiten', 'link_type' => 'news']);
        $this->contentTile($page, ['heading' => 'Extern', 'link_type' => 'url', 'link_url' => 'https://example.org/x']);
        $this->contentTile($page, ['heading' => 'Ohne Link', 'link_type' => 'none']);

        $html = $this->get('/seite')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('homepage.section', 'ueber-uns').'#team"', $html);
        $this->assertStringContainsString('href="'.route('homepage.courses.index').'"', $html);
        $this->assertStringContainsString('href="'.route('homepage.calendar').'"', $html);
        $this->assertStringContainsString('href="'.route('homepage.news.index').'"', $html);
        $this->assertStringContainsString('href="https://example.org/x"', $html);
        $this->assertStringContainsString('Ohne Link', $html);
    }

    public function test_section_tiles_fall_back_to_the_tile_defaults_of_the_linked_section(): void
    {
        $about = Section::factory()->create([
            'title' => 'Über uns',
            'teaser' => 'Wer wir sind',
            'tile_image_path' => 'homepage/about.jpg',
            'tile_image_alt' => 'Unser Haus',
            'layout' => 'highlighted',
        ]);
        $page = $this->page();

        Tile::factory()->for($page)->linkingTo($about)->create();
        Tile::factory()->for($page)->linkingTo($about)->create(['heading' => 'Eigene Überschrift', 'text' => '<p>Eigener <strong>Text</strong></p>', 'style' => 'standard']);

        $this->get('/seite')
            ->assertOk()
            ->assertSeeInOrder(['Über uns', 'Wer wir sind', 'Eigene Überschrift', 'Eigener'])
            ->assertSee('<strong>Text</strong>', false)
            ->assertSee('alt="Unser Haus"', false)
            ->assertSee('bg-tile-highlight!');

        $this->assertSame(TileStyle::Highlighted, TileGrid::items($page)[0]->props['card']->style);
        $this->assertSame(TileStyle::Standard, TileGrid::items($page)[1]->props['card']->style);
    }

    public function test_hidden_tiles_and_tiles_linking_to_hidden_or_deleted_sections_are_left_out(): void
    {
        $hidden = Section::factory()->hidden()->create(['title' => 'Versteckt']);
        $deleted = Section::factory()->create(['title' => 'Gelöscht']);
        $page = $this->page();

        Tile::factory()->for($page)->linkingTo($hidden)->create();
        Tile::factory()->for($page)->linkingTo($deleted)->create();
        $this->contentTile($page, ['heading' => 'Ausgeblendet', 'is_visible' => false]);
        $this->contentTile($page, ['heading' => 'Bleibt']);

        $deleted->delete();

        $this->get('/seite')
            ->assertOk()
            ->assertDontSee('Versteckt')
            ->assertDontSee('Gelöscht')
            ->assertDontSee('Ausgeblendet')
            ->assertSee('Bleibt');
    }

    public function test_tiles_are_rendered_in_order_with_their_size(): void
    {
        $page = $this->page();

        $this->contentTile($page, ['heading' => 'Zweite', 'sort' => 2, 'width' => 4, 'height' => 2]);
        $this->contentTile($page, ['heading' => 'Erste', 'sort' => 1]);
        Tile::factory()->for($page)->text('<p>Ganz unten</p>')->create(['sort' => 3]);

        $this->get('/seite')
            ->assertOk()
            ->assertSeeInOrder(['Erste', 'Zweite', 'Ganz unten'])
            ->assertSee('sm:col-span-2 lg:col-span-4')
            ->assertSee('lg:row-span-2');
    }

    public function test_hero_tile_shows_eyebrow_heading_and_text(): void
    {
        $this->contentTile($this->page(), [
            'style' => 'hero',
            'eyebrow' => 'Offiziell anerkannt',
            'heading' => 'Für eine Zukunft voller Chancen.',
            'text' => '<p>Alle Angebote sind kostenlos.</p>',
            'width' => 6,
        ]);

        $this->get('/seite')
            ->assertOk()
            ->assertSeeInOrder(['Offiziell anerkannt', 'Für eine Zukunft voller Chancen.', 'Alle Angebote sind kostenlos.'])
            ->assertSee('sm:text-5xl')
            ->assertSee('lg:col-span-6');
    }

    public function test_dangerous_links_and_markup_are_not_rendered(): void
    {
        $page = $this->page();

        $this->contentTile($page, [
            'heading' => 'Gefährlich',
            'text' => '<p>Hallo<script>alert(1)</script></p>',
            'link_type' => 'url',
            'link_url' => 'javascript:alert(2)',
        ]);
        Tile::factory()->for($page)->ofType(TileType::Button, ['label' => 'Böser Button', 'target_type' => 'url', 'url' => 'javascript:alert(3)'])->create();

        $this->get('/seite')
            ->assertOk()
            ->assertSee('Gefährlich')
            ->assertDontSee('<script>alert(1)', false)
            ->assertDontSee('javascript:alert', false)
            ->assertDontSee('Böser Button');
    }

    public function test_button_tile_links_to_its_target(): void
    {
        $team = Section::factory()->create(['slug' => 'das-team']);

        Tile::factory()->for($this->page())->ofType(TileType::Button, ['label' => 'Unser Team kennenlernen', 'target_type' => 'section', 'section_id' => $team->id])->create();

        $this->get('/seite')
            ->assertOk()
            ->assertSee('Unser Team kennenlernen')
            ->assertSee('href="'.route('homepage.section', 'das-team').'"', false);
    }

    public function test_block_tiles_can_be_linked_via_anchor(): void
    {
        Tile::factory()->for($this->page())->ofType(TileType::Text, ['body' => '<p>Text</p>', 'anchor' => 'Über Uns!'])->create();

        $this->get('/seite')->assertOk()->assertSee('id="uber-uns"', false);
    }

    public function test_courses_tile_becomes_one_tile_per_matching_course(): void
    {
        $informatics = Interest::factory()->create(['name' => 'Informatik', 'slug' => 'informatik']);
        Course::factory()->forSchoolClasses()->create(['name' => 'Programmieren für Klassen'])->interests()->attach($informatics);
        Course::factory()->forSchoolClasses()->create(['name' => 'Löten für Klassen']);
        Course::factory()->create(['name' => 'Programmieren für Kinder'])->interests()->attach($informatics);
        Course::factory()->forSchoolClasses()->unpublished()->create(['name' => 'Entwurf'])->interests()->attach($informatics);

        Tile::factory()->for($this->page())->courses([
            'audience' => 'school_class',
            'interest_ids' => [(string) $informatics->id],
            'item_width' => 3,
        ])->create();

        $this->get('/seite')
            ->assertOk()
            ->assertSee('Programmieren für Klassen')
            ->assertDontSee('Löten für Klassen')
            ->assertDontSee('Programmieren für Kinder')
            ->assertDontSee('Entwurf')
            ->assertSee('lg:col-span-3')
            ->assertSee('href="'.e(route('homepage.courses.index', ['zielgruppe' => 'school_class', 'interesse' => 'informatik'])).'"', false);
    }

    public function test_courses_tile_combines_fixed_and_filtered_courses(): void
    {
        $fixedSecond = Course::factory()->forSchoolClasses()->create(['name' => 'Fest B']);
        $fixedFirst = Course::factory()->create(['name' => 'Fest A']);
        $unpublished = Course::factory()->unpublished()->create(['name' => 'Unveröffentlicht']);
        $excluded = Course::factory()->create(['name' => 'Ausgeschlossen']);
        Course::factory()->create(['name' => 'Gefiltert']);
        Course::factory()->forSchoolClasses()->create(['name' => 'Schulkurs']);

        $tile = Tile::factory()->for($this->page())->courses([
            'audience' => 'kids',
            'include_course_ids' => [$fixedFirst->id, $fixedSecond->id, $unpublished->id],
            'exclude_course_ids' => [$excluded->id],
            'show_all_link' => false,
        ])->create();

        $this->assertSame(['Fest A', 'Fest B', 'Gefiltert'], TileGrid::courses($tile)->pluck('name')->all());

        $this->get('/seite')
            ->assertOk()
            ->assertSeeInOrder(['Fest A', 'Fest B', 'Gefiltert'])
            ->assertDontSee('Alle Kurse ansehen');
    }

    public function test_courses_tile_with_only_fixed_courses_shows_only_these_up_to_the_limit(): void
    {
        $courses = Course::factory()->count(3)->sequence(['name' => 'Eins'], ['name' => 'Zwei'], ['name' => 'Drei'])->create();
        Course::factory()->create(['name' => 'Anderer']);

        $tile = Tile::factory()->for($this->page())->courses([
            'include_course_ids' => $courses->pluck('id')->all(),
            'limit' => 2,
        ])->create();

        $this->assertSame(['Eins', 'Zwei'], TileGrid::courses($tile)->pluck('name')->all());
    }

    public function test_courses_tile_without_matches_says_so(): void
    {
        Tile::factory()->for($this->page())->forSchools()->create();

        $this->get('/seite')->assertOk()->assertSee('Aktuell sind keine passenden Kurse geplant.');
    }

    public function test_home_is_created_once_with_hero_and_root_sections(): void
    {
        $about = Section::factory()->create(['title' => 'Über uns']);
        Section::factory()->childOf($about)->create(['title' => 'Kind']);
        Section::factory()->standalone()->create(['title' => 'Impressum']);

        $home = Section::home();

        $this->get('/')->assertOk()->assertSee('Über uns')->assertDontSee('Kind');

        $this->assertTrue($home->is(Section::home()));
        $this->assertSame(TileStyle::Hero, $home->tiles[0]->style);
        $this->assertSame(TileType::Section, $home->tiles[1]->type);
        $this->assertSame($about->id, $home->tiles[1]->link_section_id);
        $this->assertCount(2, $home->tiles);
        $this->assertSame(1, Section::query()->where('is_home', true)->count());
    }

    public function test_only_one_section_is_the_home(): void
    {
        $old = Section::factory()->create(['is_home' => true]);
        $new = Section::factory()->create(['is_home' => true]);

        $this->assertFalse($old->fresh()->is_home);
        $this->assertTrue($new->fresh()->is_home);
    }

    public function test_duplicating_inserts_a_copy_with_a_new_uuid_right_behind(): void
    {
        $page = $this->page();
        $first = $this->contentTile($page, ['heading' => 'A']);
        $this->contentTile($page, ['heading' => 'B']);

        $copy = $first->duplicate();

        $this->assertNotSame($first->uuid, $copy->uuid);
        $this->assertSame(['A', 'A', 'B'], $page->tiles()->pluck('heading')->all());
    }

    protected function cockpitUser(): User
    {
        $user = User::factory()->create();

        foreach (['ViewAny', 'View', 'Update', 'Delete', 'DeleteAny', 'Reorder'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:Section"));
        }

        return $user;
    }

    public function test_home_page_tiles_are_managed_in_the_cockpit_and_home_cannot_be_deleted(): void
    {
        $user = $this->cockpitUser();
        $this->actingAs($user);

        $about = Section::factory()->create(['title' => 'Über uns']);
        $home = Section::home();

        Livewire::test(EditSection::class, ['record' => $home->getRouteKey()])
            ->assertFormFieldHidden('slug')
            ->assertFormFieldHidden('parent_id')
            ->assertActionHidden(DeleteAction::class)
            ->assertSeeLivewire(TilesRelationManager::class);

        Livewire::test(TilesRelationManager::class, ['ownerRecord' => $home, 'pageClass' => EditSection::class])
            ->assertOk()
            ->assertCanSeeTableRecords($home->tiles)
            ->callAction(TestAction::make('create_content')->table(), [
                'style' => 'standard',
                'heading' => 'Neu im Cockpit',
                'link_type' => 'section',
                'link_section_id' => $about->id,
                'width' => 3,
                'height' => 1,
                'is_visible' => true,
            ])
            ->assertHasNoFormErrors();

        $tile = $home->tiles()->where('heading', 'Neu im Cockpit')->firstOrFail();

        $this->assertSame(TileType::Content, $tile->type);
        $this->assertSame(3, $tile->width);
        $this->assertSame($home->tiles()->max('sort'), $tile->sort);

        $this->get('/')->assertSee('Neu im Cockpit');

        $this->assertFalse($user->can('delete', $home));
    }

    public function test_cockpit_creates_section_and_courses_tiles(): void
    {
        $this->actingAs($this->cockpitUser());

        $page = $this->page();
        $about = Section::factory()->create(['title' => 'Über uns']);
        $informatics = Interest::factory()->create();

        Livewire::test(TilesRelationManager::class, ['ownerRecord' => $page, 'pageClass' => EditSection::class])
            ->callAction(TestAction::make('create_section')->table(), [
                'link_section_id' => $about->id,
            ])
            ->assertHasNoFormErrors()
            ->callAction(TestAction::make('create_courses')->table(), [
                'settings' => [
                    'audience' => 'school_class',
                    'interest_ids' => [$informatics->id],
                    'limit' => 4,
                    'item_width' => 3,
                    'item_height' => 1,
                ],
            ])
            ->assertHasNoFormErrors();

        [$sectionTile, $coursesTile] = $page->tiles()->get()->all();

        $this->assertSame(TileType::Section, $sectionTile->type);
        $this->assertSame('section', $sectionTile->link_type);
        $this->assertSame($about->id, $sectionTile->link_section_id);
        $this->assertSame(TileType::Courses, $coursesTile->type);
        $this->assertSame('school_class', $coursesTile->setting('audience'));
        $this->assertSame(4, (int) $coursesTile->setting('limit'));
        $this->assertSame(6, $coursesTile->width);
    }

    public function test_cockpit_edits_block_tiles_and_validates_alt_texts(): void
    {
        $this->actingAs($this->cockpitUser());

        $page = $this->page();
        $tile = Tile::factory()->for($page)->text('<p>Alt</p>')->create();

        Livewire::test(TilesRelationManager::class, ['ownerRecord' => $page, 'pageClass' => EditSection::class])
            ->callAction(TestAction::make('edit')->table($tile), [
                'settings' => ['body' => '<p>Neuer Text</p>'],
            ])
            ->assertHasNoFormErrors()
            ->callAction(TestAction::make('create_image')->table(), [
                'settings' => ['image' => ['homepage/bild.jpg'], 'alt' => ''],
            ])
            ->assertHasFormErrors(['settings.alt']);

        $this->assertStringContainsString('Neuer Text', (string) $tile->fresh()->setting('body'));
        $this->assertSame(1, $page->tiles()->count());
    }

    public function test_cockpit_duplicates_and_reorders_tiles(): void
    {
        $this->actingAs($this->cockpitUser());

        $page = $this->page();
        $first = $this->contentTile($page, ['heading' => 'A']);
        $second = $this->contentTile($page, ['heading' => 'B']);

        Livewire::test(TilesRelationManager::class, ['ownerRecord' => $page, 'pageClass' => EditSection::class])
            ->callAction(TestAction::make('duplicate')->table($first))
            ->call('reorderTable', [(string) $second->id, (string) $first->id]);

        $this->assertSame(['B', 'A'], $page->tiles()->whereIn('id', [$first->id, $second->id])->pluck('heading')->all());
        $this->assertSame(3, $page->tiles()->count());
    }

    public function test_users_without_update_permission_cannot_add_tiles(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('ViewAny:Section'), Permission::findOrCreate('View:Section'));
        $this->actingAs($user);

        $page = $this->page();

        $this->assertFalse($user->can('create', Tile::class));
        $this->assertFalse($user->can('update', $this->contentTile($page)));
    }
}
