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
        $about = Section::factory()->create(['slug' => 'ueber-uns']);
        Section::factory()->childOf($about)->create();

        $this->actingAs($this->editor());

        $this->get('/')->assertOk()->assertSee('Kacheln anordnen')->assertDontSeeLivewire(TileEditor::class);
        $this->get('/?anordnen=1')->assertOk()->assertSee('Fertig')->assertSeeLivewire(TileEditor::class);
        $this->get('/ueber-uns?anordnen=1')->assertOk()->assertSeeLivewire(TileEditor::class);
    }

    public function test_sort_reorders_tiles_and_keeps_hidden_siblings(): void
    {
        $first = Section::factory()->create(['sort' => 1]);
        $hidden = Section::factory()->hidden()->create(['sort' => 2]);
        $second = Section::factory()->create(['sort' => 3]);
        $third = Section::factory()->create(['sort' => 4]);

        $this->actingAs($this->editor());

        Livewire::test(TileEditor::class)
            ->call('sort', $third->id, 0);

        $this->assertSame(1, $third->fresh()->sort);
        $this->assertSame(3, $first->fresh()->sort);
        $this->assertSame(4, $second->fresh()->sort);
        $this->assertSame(2, $hidden->fresh()->sort);
    }

    public function test_sort_spreads_duplicate_sort_values(): void
    {
        $first = Section::factory()->create(['sort' => 0]);
        $second = Section::factory()->create(['sort' => 0]);

        $this->actingAs($this->editor());

        Livewire::test(TileEditor::class)
            ->call('sort', $second->id, 0);

        $this->assertSame(0, $second->fresh()->sort);
        $this->assertSame(1, $first->fresh()->sort);
    }

    public function test_resize_saves_tile_size(): void
    {
        $section = Section::factory()->create();

        $this->actingAs($this->editor());

        Livewire::test(TileEditor::class)
            ->call('resize', $section->id, 4, 2)
            ->assertHasNoErrors();

        $this->assertSame(4, $section->fresh()->tile_width);
        $this->assertSame(2, $section->fresh()->tile_height);
    }

    public function test_resize_rejects_invalid_sizes(): void
    {
        $section = Section::factory()->create();

        $this->actingAs($this->editor());

        Livewire::test(TileEditor::class)
            ->call('resize', $section->id, 5, 3)
            ->assertHasErrors(['width', 'height']);

        $this->assertSame(2, $section->fresh()->tile_width);
    }

    public function test_tiles_of_other_levels_cannot_be_changed(): void
    {
        $parent = Section::factory()->create();
        $child = Section::factory()->childOf($parent)->create(['sort' => 5]);

        $this->actingAs($this->editor());

        Livewire::test(TileEditor::class)
            ->call('resize', $child->id, 6, 1)
            ->assertNotFound();

        Livewire::test(TileEditor::class)
            ->call('sort', $child->id, 0)
            ->assertNotFound();

        $this->assertSame(2, $child->fresh()->tile_width);
        $this->assertSame(5, $child->fresh()->sort);
    }

    public function test_user_without_reorder_permission_cannot_use_editor(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('Update:Section'));

        $this->actingAs($user);

        Livewire::test(TileEditor::class)->assertForbidden();
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
