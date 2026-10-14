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
}
