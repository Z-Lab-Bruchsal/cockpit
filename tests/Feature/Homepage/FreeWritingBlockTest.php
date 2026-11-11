<?php

namespace Tests\Feature\Homepage;

use App\Filament\Resources\Sections\Pages\EditSection;
use App\Filament\Resources\Sections\RelationManagers\TilesRelationManager;
use App\Models\Section;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FreeWritingBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    private function freeSection(string $html): Section
    {
        return Section::factory()->withContent([['type' => 'free', 'data' => ['body' => $html]]])->create([
            'slug' => 'frei',
        ]);
    }

    public function test_renders_columns_colors_tables_and_collapsible_sections(): void
    {
        $this->freeSection(
            '<div class="lead"><p>Einleitung</p></div>'
            .'<p style="text-align: center">Mitte <span data-color="red" class="color">rot</span> <mark>markiert</mark></p>'
            .'<div class="grid-layout" data-cols="2" data-from-breakpoint="md"><div class="grid-layout-col" data-col-span="1"><p>Spalte A</p></div><div class="grid-layout-col" data-col-span="1"><p>Spalte B</p></div></div>'
            .'<details><summary>Mehr erfahren</summary><div data-type="details-content"><p>Versteckter Text</p></div></details>'
            .'<table><tbody><tr><th><p>Kopf</p></th></tr><tr><td><p>Zelle</p></td></tr></tbody></table>'
        );

        $this->get('/frei')
            ->assertOk()
            ->assertSee('class="lead"', false)
            ->assertSee('text-align: center', false)
            ->assertSee('class="color"', false)
            ->assertSee('<mark>markiert</mark>', false)
            ->assertSee('class="grid-layout"', false)
            ->assertSee('data-from-breakpoint="md"', false)
            ->assertSee('<summary>Mehr erfahren</summary>', false)
            ->assertSee('<td><p>Zelle</p></td>', false);
    }

    public function test_inserted_images_point_to_the_public_disk(): void
    {
        Storage::disk('public')->putFileAs('homepage', UploadedFile::fake()->image('foto.jpg'), 'foto.jpg');
        $this->freeSection('<p>Text</p><img data-id="homepage/foto.jpg" src="" alt="Kinder am Lötkolben">');

        $this->get('/frei')
            ->assertOk()
            ->assertSee('src="'.Storage::disk('public')->url('homepage/foto.jpg').'"', false)
            ->assertSee('alt="Kinder am Lötkolben"', false);
    }

    public function test_scripts_and_event_handlers_are_removed(): void
    {
        $this->freeSection('<p onclick="alert(1)">Hallo</p><script>alert(2)</script><a href="javascript:alert(3)">Link</a>');

        $this->get('/frei')
            ->assertOk()
            ->assertSee('Hallo')
            ->assertDontSee('alert(1)', false)
            ->assertDontSee('alert(2)', false)
            ->assertDontSee('javascript:', false);
    }

    public function test_block_is_editable_in_the_cockpit(): void
    {
        $user = User::factory()->create();
        foreach (['ViewAny', 'View', 'Update'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:Section"));
        }
        $section = $this->freeSection('<p>Hallo Welt</p>');

        $this->actingAs($user)
            ->get("/cockpit/sections/{$section->id}/edit")
            ->assertOk();

        $tile = $section->tiles()->firstOrFail();

        Livewire::test(TilesRelationManager::class, ['ownerRecord' => $section, 'pageClass' => EditSection::class])
            ->assertSee('Freies Schreiben')
            ->assertSee('Hallo Welt')
            ->callAction(TestAction::make('edit')->table($tile), ['settings' => ['body' => '<p>Neu geschrieben</p>']])
            ->assertHasNoFormErrors();

        $this->assertStringContainsString('Neu geschrieben', (string) $tile->fresh()->setting('body'));
    }
}
