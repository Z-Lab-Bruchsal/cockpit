<?php

namespace Tests\Feature\Homepage;

use App\Enums\TileType;
use App\Filament\Pages\Inline\EditOfferInline;
use App\Filament\Pages\Inline\EditTileInline;
use App\Livewire\Homepage\SectionTileEditor;
use App\Models\Contact;
use App\Models\CourseInstance;
use App\Models\Section;
use App\Models\Tile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SectionTileEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function editor(): User
    {
        $user = User::factory()->create();

        foreach (['ViewAny', 'Update', 'Reorder'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:Section"));
        }

        return $user;
    }

    /**
     * A page with one navigation tile per given section.
     *
     * @param  list<Section>  $targets
     * @param  array<string, mixed>  $attributes
     */
    protected function pageWithTiles(array $targets, array $attributes = []): Section
    {
        $page = Section::factory()->create(['slug' => 'kacheln', ...$attributes]);
        $page->addTilesFor($targets);

        return $page;
    }

    /**
     * @return list<string>
     */
    protected function tileUuids(Section $page): array
    {
        return $page->tiles()->pluck('uuid')->all();
    }

    protected function editorFor(Section $page): mixed
    {
        return Livewire::test(SectionTileEditor::class, ['sectionId' => $page->id]);
    }

    public function test_guests_do_not_get_the_editor(): void
    {
        Section::factory()->create(['title' => 'Über uns']);

        $this->get('/?bearbeiten=1')
            ->assertOk()
            ->assertSee('Über uns')
            ->assertDontSee('Bearbeiten-Modus')
            ->assertDontSeeLivewire(SectionTileEditor::class);
    }

    public function test_contacts_do_not_get_the_editor(): void
    {
        Section::factory()->create();

        $this->actingAs(Contact::factory()->create(), 'contact')
            ->get('/?bearbeiten=1')
            ->assertOk()
            ->assertDontSee('Bearbeiten-Modus')
            ->assertDontSeeLivewire(SectionTileEditor::class);
    }

    public function test_cockpit_users_without_permission_do_not_get_the_editor(): void
    {
        Section::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get('/?bearbeiten=1')
            ->assertOk()
            ->assertDontSee('Bearbeiten-Modus')
            ->assertDontSeeLivewire(SectionTileEditor::class);
    }

    public function test_editor_gets_the_tile_editor_in_edit_mode(): void
    {
        $child = Section::factory()->create();
        $this->pageWithTiles([$child], ['slug' => 'ueber-uns']);

        $this->actingAs($this->editor());

        $this->get('/')
            ->assertOk()
            ->assertSee('>Bearbeiten<', false)
            ->assertDontSee('Kacheln anordnen')
            ->assertDontSee('Footer anordnen')
            ->assertDontSeeLivewire(SectionTileEditor::class);
        $this->get('/?bearbeiten=1')->assertOk()->assertSee('Bearbeiten-Modus')->assertSee('Fertig')->assertSeeLivewire(SectionTileEditor::class);
        $this->get('/?anordnen=1')->assertOk()->assertSeeLivewire(SectionTileEditor::class);
        $this->get('/ueber-uns?bearbeiten=1')
            ->assertOk()
            ->assertSeeLivewire(SectionTileEditor::class)
            ->assertSee('Kachel hinzufügen')
            ->assertSee('open-inline-editor');
    }

    public function test_editor_shows_hidden_tiles_and_one_placeholder_per_courses_tile(): void
    {
        $page = Section::factory()->create();
        Tile::factory()->for($page)->hidden()->create(['heading' => 'Ausgeblendete Kachel']);
        Tile::factory()->for($page)->forSchools()->create();

        $this->actingAs($this->editor());

        $this->editorFor($page)
            ->assertSee('Ausgeblendete Kachel')
            ->assertSee('opacity-50')
            ->assertSee('Kurse · Schulklassen')
            ->assertSee('auf der Seite erscheint an dieser Stelle je Kurs eine eigene Kachel');
    }

    public function test_sort_moves_tiles_among_all_tiles_including_hidden_ones(): void
    {
        $first = Section::factory()->create();
        $hidden = Section::factory()->hidden()->create();
        $second = Section::factory()->create();
        $third = Section::factory()->create();
        $page = $this->pageWithTiles([$first, $hidden, $second, $third]);
        [$firstId, $hiddenId, $secondId, $thirdId] = $this->tileUuids($page);

        $this->actingAs($this->editor());

        $this->editorFor($page)->call('sort', $thirdId, 0);

        $this->assertSame([$thirdId, $firstId, $hiddenId, $secondId], $this->tileUuids($page));
    }

    public function test_sort_moves_tile_to_the_end(): void
    {
        $page = $this->pageWithTiles([Section::factory()->create(), Section::factory()->create()]);
        [$firstId, $secondId] = $this->tileUuids($page);

        $this->actingAs($this->editor());

        $this->editorFor($page)->call('sort', $firstId, 5);

        $this->assertSame([$secondId, $firstId], $this->tileUuids($page));
    }

    public function test_resize_saves_tile_size(): void
    {
        $page = $this->pageWithTiles([Section::factory()->create()]);
        [$tileId] = $this->tileUuids($page);

        $this->actingAs($this->editor());

        $this->editorFor($page)
            ->call('resize', $tileId, 4, 2)
            ->assertHasNoErrors();

        $tile = $page->tiles()->first();

        $this->assertSame(4, $tile->width);
        $this->assertSame(2, $tile->height);
    }

    public function test_resize_rejects_invalid_sizes(): void
    {
        $page = $this->pageWithTiles([Section::factory()->create()]);
        [$tileId] = $this->tileUuids($page);

        $this->actingAs($this->editor());

        $this->editorFor($page)
            ->call('resize', $tileId, 5, 3)
            ->assertHasErrors(['width', 'height']);

        $this->assertSame(2, $page->tiles()->first()->width);
    }

    public function test_tiles_can_be_hidden_duplicated_and_deleted(): void
    {
        $page = Section::factory()->create();
        $tile = Tile::factory()->for($page)->create(['heading' => 'Original']);
        $other = Tile::factory()->for($page)->create(['heading' => 'Andere']);

        $this->actingAs($this->editor());

        $editor = $this->editorFor($page)->call('toggleVisibility', $tile->uuid);
        $this->assertFalse($tile->fresh()->is_visible);

        $editor->call('toggleVisibility', $tile->uuid);
        $this->assertTrue($tile->fresh()->is_visible);

        $editor->call('duplicate', $tile->uuid);
        $this->assertSame(['Original', 'Original', 'Andere'], $page->tiles()->pluck('heading')->all());

        $editor->call('delete', $other->uuid);
        $this->assertModelMissing($other);
        $this->assertSame(2, $page->tiles()->count());
    }

    public function test_tiles_of_other_sections_cannot_be_changed(): void
    {
        $page = $this->pageWithTiles([Section::factory()->create()]);
        $foreign = Tile::factory()->create(['width' => 2]);

        $this->actingAs($this->editor());

        foreach ([['resize', $foreign->uuid, 6, 1], ['sort', $foreign->uuid, 0], ['toggleVisibility', $foreign->uuid], ['duplicate', $foreign->uuid], ['delete', $foreign->uuid]] as $call) {
            $this->editorFor($page)->call(...$call)->assertNotFound();
        }

        $this->assertModelExists($foreign);
        $this->assertSame(2, $foreign->fresh()->width);
        $this->assertTrue($foreign->fresh()->is_visible);
        $this->assertSame(1, Tile::query()->where('section_id', $foreign->section_id)->count());
    }

    public function test_offers_keep_their_pencils_in_the_tile_editor(): void
    {
        $page = Section::factory()->withContent([['type' => 'offers', 'data' => ['show_filter_tabs' => false]]])->create(['slug' => 'angebote']);
        $instance = CourseInstance::factory()->withDates()->create(['title' => 'Löten']);

        $user = $this->editor();
        foreach (['ViewAny', 'View', 'Update'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:CourseInstance"));
        }

        $this->actingAs($user)
            ->get('/angebote?bearbeiten=1')
            ->assertOk()
            ->assertSeeLivewire(SectionTileEditor::class)
            ->assertSee('Löten')
            ->assertSee(EditOfferInline::getUrl(['instance' => $instance->id]));
    }

    public function test_editors_without_reorder_permission_get_pencils_instead_of_the_tile_editor(): void
    {
        $page = Section::factory()->create(['slug' => 'seite']);
        $tile = Tile::factory()->for($page)->create(['heading' => 'Kachel']);

        $user = User::factory()->create();
        foreach (['ViewAny', 'View', 'Update'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:Section"));
        }

        $this->actingAs($user)
            ->get('/seite?bearbeiten=1')
            ->assertOk()
            ->assertDontSeeLivewire(SectionTileEditor::class)
            ->assertSee(EditTileInline::getUrl(['tile' => $tile->uuid]));
    }

    public function test_user_without_reorder_permission_cannot_use_editor(): void
    {
        $page = $this->pageWithTiles([Section::factory()->create()]);

        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('Update:Section'));

        $this->actingAs($user);

        $this->editorFor($page)->assertForbidden();
    }

    public function test_add_links_open_the_inline_create_page_for_each_type(): void
    {
        $page = Section::factory()->create();

        $this->actingAs($this->editor());

        $html = $this->editorFor($page)->html();

        foreach (TileType::cases() as $type) {
            $this->assertStringContainsString(e($type->getLabel()), $html);
        }

        $this->assertStringContainsString('homepage\/tile\/new?section='.$page->id, $html);
        $this->assertStringContainsString('type=courses', $html);
    }

    public function test_tiles_render_with_their_size(): void
    {
        Section::factory()->create(['tile_width' => 6, 'tile_height' => 2]);

        $this->get('/')
            ->assertOk()
            ->assertSee('lg:col-span-6')
            ->assertSee('lg:row-span-2');
    }
}
