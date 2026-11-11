<?php

namespace Tests\Feature\Homepage;

use App\Livewire\Homepage\TileEditor;
use App\Models\Contact;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TileEditorTest extends TestCase
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
     * A page with one tile grid block linking to the given sections.
     *
     * @param  list<Section>  $targets
     */
    protected function pageWithTiles(array $targets, array $attributes = []): Section
    {
        return Section::factory()->create([
            'slug' => 'kacheln',
            ...$attributes,
            'content' => [Section::tilesBlockFor($targets)],
        ]);
    }

    /**
     * @return list<string>
     */
    protected function tileIds(Section $page): array
    {
        return array_column($page->fresh()->content[0]['data']['tiles'], 'id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function tileData(Section $page, int $index): array
    {
        return $page->fresh()->content[0]['data']['tiles'][$index];
    }

    protected function editorFor(Section $page): mixed
    {
        return Livewire::test(TileEditor::class, [
            'sectionId' => $page->id,
            'blockId' => $page->fresh()->content[0]['data']['block_id'],
        ]);
    }

    public function test_guests_do_not_get_the_editor(): void
    {
        Section::factory()->create(['title' => 'Über uns']);

        $this->get('/?anordnen=1')
            ->assertOk()
            ->assertSee('Über uns')
            ->assertDontSee('Kacheln anordnen')
            ->assertDontSeeLivewire(TileEditor::class);
    }

    public function test_contacts_do_not_get_the_editor(): void
    {
        Section::factory()->create();

        $this->actingAs(Contact::factory()->create(), 'contact')
            ->get('/?anordnen=1')
            ->assertOk()
            ->assertDontSee('Kacheln anordnen')
            ->assertDontSeeLivewire(TileEditor::class);
    }

    public function test_cockpit_users_without_permission_do_not_get_the_editor(): void
    {
        Section::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get('/?anordnen=1')
            ->assertOk()
            ->assertDontSee('Kacheln anordnen')
            ->assertDontSeeLivewire(TileEditor::class);
    }

    public function test_editor_sees_toolbar_and_editor_in_arrange_mode(): void
    {
        $child = Section::factory()->create();
        $this->pageWithTiles([$child], ['slug' => 'ueber-uns']);

        $this->actingAs($this->editor());

        $this->get('/')->assertOk()->assertSee('Kacheln anordnen')->assertDontSeeLivewire(TileEditor::class);
        $this->get('/?anordnen=1')->assertOk()->assertSee('Fertig')->assertSeeLivewire(TileEditor::class);
        $this->get('/ueber-uns?anordnen=1')->assertOk()->assertSeeLivewire(TileEditor::class);
    }

    public function test_sort_reorders_tiles_and_keeps_hidden_ones_in_place(): void
    {
        $first = Section::factory()->create();
        $hidden = Section::factory()->hidden()->create();
        $second = Section::factory()->create();
        $third = Section::factory()->create();
        $page = $this->pageWithTiles([$first, $hidden, $second, $third]);
        [$firstId, $hiddenId, $secondId, $thirdId] = $this->tileIds($page);

        $this->actingAs($this->editor());

        $this->editorFor($page)->call('sort', $thirdId, 0);

        $this->assertSame([$thirdId, $firstId, $hiddenId, $secondId], $this->tileIds($page));
    }

    public function test_sort_moves_tile_to_the_end(): void
    {
        $first = Section::factory()->create();
        $second = Section::factory()->create();
        $page = $this->pageWithTiles([$first, $second]);
        [$firstId, $secondId] = $this->tileIds($page);

        $this->actingAs($this->editor());

        $this->editorFor($page)->call('sort', $firstId, 1);

        $this->assertSame([$secondId, $firstId], $this->tileIds($page));
    }

    public function test_resize_saves_tile_size(): void
    {
        $page = $this->pageWithTiles([Section::factory()->create()]);
        [$tileId] = $this->tileIds($page);

        $this->actingAs($this->editor());

        $this->editorFor($page)
            ->call('resize', $tileId, 4, 2)
            ->assertHasNoErrors();

        $this->assertSame(4, $this->tileData($page, 0)['width']);
        $this->assertSame(2, $this->tileData($page, 0)['height']);
    }

    public function test_resize_rejects_invalid_sizes(): void
    {
        $page = $this->pageWithTiles([Section::factory()->create()]);
        [$tileId] = $this->tileIds($page);

        $this->actingAs($this->editor());

        $this->editorFor($page)
            ->call('resize', $tileId, 5, 3)
            ->assertHasErrors(['width', 'height']);

        $this->assertSame(2, $this->tileData($page, 0)['width']);
    }

    public function test_tiles_of_other_blocks_cannot_be_changed(): void
    {
        $page = $this->pageWithTiles([Section::factory()->create()]);
        $other = Section::factory()->create(['slug' => 'andere', 'content' => [Section::tilesBlockFor([Section::factory()->create()])]]);
        $foreignTileId = $other->content[0]['data']['tiles'][0]['id'];

        $this->actingAs($this->editor());

        $this->editorFor($page)
            ->call('resize', $foreignTileId, 6, 1)
            ->assertNotFound();

        $this->editorFor($page)
            ->call('sort', $foreignTileId, 0)
            ->assertNotFound();

        $this->assertSame(2, $other->fresh()->content[0]['data']['tiles'][0]['width']);
    }

    public function test_user_without_reorder_permission_cannot_use_editor(): void
    {
        $page = $this->pageWithTiles([Section::factory()->create()]);

        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('Update:Section'));

        $this->actingAs($user);

        $this->editorFor($page)->assertForbidden();
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
