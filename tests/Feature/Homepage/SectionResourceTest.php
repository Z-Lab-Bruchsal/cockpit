<?php

namespace Tests\Feature\Homepage;

use App\Filament\Resources\Sections\Pages\CreateSection;
use App\Filament\Resources\Sections\Pages\EditSection;
use App\Filament\Resources\Sections\Pages\ListSections;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SectionResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $user = User::factory()->create();

        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Reorder'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:Section"));
        }

        $this->actingAs($user);
    }

    public function test_pages_render(): void
    {
        $section = Section::factory()->create();

        $this->get('/cockpit/sections')->assertOk();
        $this->get('/cockpit/sections/create')->assertOk();
        $this->get("/cockpit/sections/{$section->id}/edit")->assertOk();
    }

    public function test_user_without_permission_cannot_access(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/cockpit/sections')->assertForbidden();
    }

    public function test_can_create_nested_section(): void
    {
        $parent = Section::factory()->create();

        Livewire::test(CreateSection::class)
            ->fillForm([
                'title' => 'Workshops',
                'slug' => 'workshops',
                'parent_id' => $parent->id,
                'layout' => 'highlighted',
                'content' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Section::class, [
            'slug' => 'workshops',
            'parent_id' => $parent->id,
            'layout' => 'highlighted',
        ]);
    }

    public function test_can_set_tile_size(): void
    {
        $section = Section::factory()->create();

        Livewire::test(EditSection::class, ['record' => $section->getRouteKey()])
            ->fillForm(['tile_width' => 6, 'tile_height' => 2])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(6, $section->fresh()->tile_width);
        $this->assertSame(2, $section->fresh()->tile_height);

        Livewire::test(EditSection::class, ['record' => $section->getRouteKey()])
            ->fillForm(['tile_width' => 5])
            ->call('save')
            ->assertHasFormErrors(['tile_width']);
    }

    public function test_slug_must_not_be_reserved_or_duplicate(): void
    {
        Section::factory()->create(['slug' => 'vorhanden']);

        Livewire::test(CreateSection::class)
            ->fillForm(['title' => 'Cockpit', 'slug' => 'cockpit'])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'not_in']);

        Livewire::test(CreateSection::class)
            ->fillForm(['title' => 'Doppelt', 'slug' => 'vorhanden'])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    public function test_section_cannot_become_child_of_its_own_descendant(): void
    {
        $root = Section::factory()->create();
        $child = Section::factory()->childOf($root)->create();
        $other = Section::factory()->create();

        $component = Livewire::test(EditSection::class, ['record' => $root->id]);

        $options = $component->instance()->form->getComponent('parent_id')->getOptions();

        $this->assertArrayNotHasKey($root->id, $options);
        $this->assertArrayNotHasKey($child->id, $options);
        $this->assertArrayHasKey($other->id, $options);
    }

    public function test_table_lists_sections(): void
    {
        $sections = Section::factory()->count(3)->create();

        Livewire::test(ListSections::class)
            ->assertCanSeeTableRecords($sections);
    }

    public function test_table_lists_children_directly_below_their_parent(): void
    {
        $about = Section::factory()->create(['title' => 'Über uns', 'sort' => 2]);
        $courses = Section::factory()->create(['title' => 'Kurse', 'sort' => 1]);
        $team = Section::factory()->create(['title' => 'Team', 'parent_id' => $about->id, 'sort' => 0]);
        $robotics = Section::factory()->create(['title' => 'Robotik', 'parent_id' => $courses->id, 'sort' => 5]);
        $drones = Section::factory()->create(['title' => 'Drohnen', 'parent_id' => $courses->id, 'sort' => 3]);
        $history = Section::factory()->create(['title' => 'Geschichte', 'parent_id' => $team->id, 'sort' => 9]);

        Livewire::test(ListSections::class)
            ->assertCanSeeTableRecords([$courses, $drones, $robotics, $about, $team, $history], inOrder: true);
    }

    public function test_reordering_keeps_children_below_their_parent(): void
    {
        $first = Section::factory()->create(['title' => 'Erster', 'sort' => 1]);
        $child = Section::factory()->create(['title' => 'Kind', 'parent_id' => $first->id, 'sort' => 1]);
        $second = Section::factory()->create(['title' => 'Zweiter', 'sort' => 2]);

        // Drag the first main section below the second one: its child moves along.
        Livewire::test(ListSections::class)
            ->call('toggleTableReordering')
            ->call('reorderTable', [(string) $child->id, (string) $second->id, (string) $first->id])
            ->call('toggleTableReordering')
            ->assertCanSeeTableRecords([$second, $first, $child], inOrder: true);

        $this->assertSame(
            [$second->id, $first->id, $child->id],
            Section::query()->orderBy('sort')->pluck('id')->all(),
        );
    }

    public function test_entering_reorder_mode_aligns_sort_with_the_tree(): void
    {
        $parent = Section::factory()->create(['sort' => 5]);
        $child = Section::factory()->create(['parent_id' => $parent->id, 'sort' => 0]);
        $other = Section::factory()->create(['sort' => 6]);

        Livewire::test(ListSections::class)
            ->call('toggleTableReordering')
            ->assertCanSeeTableRecords([$parent, $child, $other], inOrder: true);

        $this->assertSame([1, 2, 3], [$parent->fresh()->sort, $child->fresh()->sort, $other->fresh()->sort]);
    }
}
