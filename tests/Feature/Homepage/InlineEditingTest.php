<?php

namespace Tests\Feature\Homepage;

use App\Enums\CourseProminence;
use App\Filament\Pages\Inline\CreateTileInline;
use App\Filament\Pages\Inline\EditOfferInline;
use App\Filament\Pages\Inline\EditTileInline;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\Section;
use App\Models\Tile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InlineEditingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @param  list<string>  $models
     */
    protected function editor(array $models = ['Section', 'CourseInstance']): User
    {
        $user = User::factory()->create();

        foreach ($models as $model) {
            foreach (['ViewAny', 'View', 'Update'] as $ability) {
                $user->givePermissionTo(Permission::findOrCreate("{$ability}:{$model}"));
            }
        }

        return $user;
    }

    protected function page(): Section
    {
        return Section::factory()->withContent([
            ['type' => 'text', 'data' => ['body' => '<p>Erster Block</p>']],
            ['type' => 'text', 'data' => ['body' => '<p>Zweiter Block</p>']],
            ['type' => 'offers', 'data' => ['show_filter_tabs' => false]],
        ])->create([
            'slug' => 'seite',
            'title' => 'Meine Seite',
        ]);
    }

    protected function tileUuid(Section $section, int $index): string
    {
        return $section->tiles()->get()[$index]->uuid;
    }

    public function test_guests_and_users_without_permission_get_no_edit_mode(): void
    {
        $this->page();

        $this->get('/seite?bearbeiten=1')->assertOk()->assertDontSee('Kachel bearbeiten')->assertDontSee('Bearbeiten-Modus');

        $this->actingAs(User::factory()->create())
            ->get('/seite?bearbeiten=1')
            ->assertOk()
            ->assertDontSee('Kachel bearbeiten')
            ->assertDontSee('>Bearbeiten<', false);
    }

    public function test_editor_switches_on_edit_mode_and_gets_a_pencil_per_tile_and_offer(): void
    {
        $page = $this->page();
        $instance = CourseInstance::factory()->withDates()->create(['title' => 'Löten']);

        $this->actingAs($this->editor());

        $this->get('/seite')->assertOk()->assertSee('>Bearbeiten<', false)->assertDontSee('Kachel bearbeiten');

        $this->get('/seite?bearbeiten=1')
            ->assertOk()
            ->assertSee('Bearbeiten-Modus')
            ->assertSee(EditTileInline::getUrl(['tile' => $this->tileUuid($page, 1)]))
            ->assertSee(EditOfferInline::getUrl(['instance' => $instance->id]))
            ->assertSee('Angebot bearbeiten');

        $this->get($instance->url().'?bearbeiten=1')
            ->assertOk()
            ->assertSee(EditOfferInline::getUrl(['instance' => $instance->id]));
    }

    public function test_users_who_may_only_edit_offers_get_no_tile_pencils(): void
    {
        $this->page();
        CourseInstance::factory()->withDates()->create();

        $this->actingAs($this->editor(['CourseInstance']))
            ->get('/seite?bearbeiten=1')
            ->assertOk()
            ->assertDontSee('Kachel bearbeiten')
            ->assertSee('Angebot bearbeiten');
    }

    public function test_tile_editor_saves_only_its_tile(): void
    {
        $page = $this->page();
        $uuid = $this->tileUuid($page, 1);

        $this->actingAs($this->editor());

        $this->get(EditTileInline::getUrl(['tile' => $uuid]))
            ->assertOk()
            ->assertSee('Text bearbeiten')
            ->assertDontSee('fi-sidebar', false);

        Livewire::withQueryParams(['tile' => $uuid])
            ->test(EditTileInline::class)
            ->assertSee('Seite „Meine Seite“')
            ->assertSchemaStateSet(['settings.body' => '<p>Zweiter Block</p>'])
            ->fillForm(['settings' => ['body' => '<p>Geändert</p>'], 'width' => 3])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Gespeichert');

        $tiles = $page->tiles()->get();

        $this->assertSame('<p>Erster Block</p>', $tiles[0]->setting('body'));
        $this->assertStringContainsString('Geändert', $tiles[1]->setting('body'));
        $this->assertSame(3, $tiles[1]->width);
        $this->assertSame($uuid, $tiles[1]->uuid);
        $this->assertSame('offers', $tiles[2]->type->value);
    }

    public function test_tile_editor_rejects_unknown_tiles_and_missing_permission(): void
    {
        $page = $this->page();

        $this->actingAs($this->editor());

        Livewire::withQueryParams(['tile' => 'gibt-es-nicht'])
            ->test(EditTileInline::class)
            ->assertNotFound();

        $this->actingAs($this->editor(['CourseInstance']));

        Livewire::withQueryParams(['tile' => $this->tileUuid($page, 0)])
            ->test(EditTileInline::class)
            ->assertForbidden();
    }

    public function test_new_tiles_are_added_at_the_end_of_the_page(): void
    {
        $page = $this->page();

        $this->actingAs($this->editor());

        $this->get(CreateTileInline::getUrl(['section' => $page->id, 'type' => 'content']))
            ->assertOk()
            ->assertSee('Inhalt hinzufügen');

        Livewire::withQueryParams(['section' => $page->id, 'type' => 'content'])
            ->test(CreateTileInline::class)
            ->fillForm(['heading' => 'Ganz neu', 'link_type' => 'none'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Gespeichert');

        $tile = $page->tiles()->get()->last();

        $this->assertSame('Ganz neu', $tile->heading);
        $this->assertSame('content', $tile->type->value);
        $this->assertSame(4, $page->tiles()->count());

        Livewire::withQueryParams(['section' => $page->id, 'type' => 'gibt-es-nicht'])
            ->test(CreateTileInline::class)
            ->assertNotFound();

        $this->actingAs($this->editor(['CourseInstance']));

        Livewire::withQueryParams(['section' => $page->id, 'type' => 'text'])
            ->test(CreateTileInline::class)
            ->assertForbidden();

        $this->assertSame(4, Tile::query()->count());
    }

    public function test_offer_editor_changes_only_the_instance(): void
    {
        $course = Course::factory()->create(['name' => 'Löten für Anfänger', 'teaser' => 'Kurstext']);
        $instance = CourseInstance::factory()->for($course)->withDates()->create();
        $sibling = CourseInstance::factory()->for($course)->withDates()->create();

        $this->actingAs($this->editor());

        $this->get(EditOfferInline::getUrl(['instance' => $instance->id]))->assertOk();

        Livewire::withQueryParams(['instance' => $instance->id])
            ->test(EditOfferInline::class)
            ->assertSee('Du bearbeitest nur die Durchführung')
            ->assertSee('Kurs-Stammdaten und andere Durchführungen')
            ->fillForm([
                'title' => 'Löten im Herbst',
                'teaser' => 'Nur für diese Durchführung',
                'prominence' => CourseProminence::Featured->value,
                'card_label' => 'Löten lernen.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $instance->refresh();

        $this->assertSame('Löten im Herbst', $instance->title);
        $this->assertSame('Nur für diese Durchführung', $instance->teaser);
        $this->assertTrue($instance->isFeatured());
        $this->assertSame('Löten lernen.', $instance->card_label);
        $this->assertSame('Löten für Anfänger', $course->fresh()->name);
        $this->assertSame('Kurstext', $course->fresh()->teaser);
        $this->assertNull($sibling->fresh()->teaser);
    }

    public function test_offer_editor_needs_update_permission(): void
    {
        $instance = CourseInstance::factory()->withDates()->create();

        $this->actingAs($this->editor(['Section']));

        Livewire::withQueryParams(['instance' => $instance->id])
            ->test(EditOfferInline::class)
            ->assertForbidden();
    }
}
