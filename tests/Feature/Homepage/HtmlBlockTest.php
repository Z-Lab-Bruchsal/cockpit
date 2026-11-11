<?php

namespace Tests\Feature\Homepage;

use App\Enums\TileType;
use App\Filament\Resources\Sections\Pages\EditSection;
use App\Filament\Resources\Sections\RelationManagers\TilesRelationManager;
use App\Models\Section;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HtmlBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function htmlSection(string $html): Section
    {
        return Section::factory()->withContent([['type' => 'html', 'data' => ['html' => $html]]])->create([
            'slug' => 'roh',
        ]);
    }

    public function test_renders_the_html_unfiltered(): void
    {
        $this->htmlSection('<div class="eigen" data-x="1"><iframe src="https://example.org/karte"></iframe><script>window.eigen = 1;</script></div>');

        $this->get('/roh')
            ->assertOk()
            ->assertSee('<div class="eigen" data-x="1">', false)
            ->assertSee('<iframe src="https://example.org/karte"></iframe>', false)
            ->assertSee('<script>window.eigen = 1;</script>', false);
    }

    public function test_empty_html_renders_nothing_broken(): void
    {
        $this->htmlSection('');

        $this->get('/roh')->assertOk();
    }

    public function test_label_shows_the_text_of_the_html(): void
    {
        $tile = $this->htmlSection('<h2>Hallo <b>Welt</b></h2>')->tiles()->firstOrFail();

        $this->assertSame(TileType::Html, $tile->type);
        $this->assertSame('Hallo Welt', $tile->label());
    }

    public function test_block_is_editable_in_the_cockpit(): void
    {
        $user = User::factory()->create();
        foreach (['ViewAny', 'View', 'Update'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:Section"));
        }
        $section = $this->htmlSection('<p>Alt</p>');
        $tile = $section->tiles()->firstOrFail();

        $this->actingAs($user);

        Livewire::test(TilesRelationManager::class, ['ownerRecord' => $section, 'pageClass' => EditSection::class])
            ->assertSee('HTML')
            ->callAction(TestAction::make('edit')->table($tile), ['settings' => ['html' => '<section id="neu">Neu</section>']])
            ->assertHasNoFormErrors();

        $this->assertSame('<section id="neu">Neu</section>', $tile->fresh()->setting('html'));
    }

    public function test_html_is_required(): void
    {
        $user = User::factory()->create();
        foreach (['ViewAny', 'View', 'Update'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:Section"));
        }
        $section = $this->htmlSection('<p>Alt</p>');
        $tile = $section->tiles()->firstOrFail();

        $this->actingAs($user);

        Livewire::test(TilesRelationManager::class, ['ownerRecord' => $section, 'pageClass' => EditSection::class])
            ->callAction(TestAction::make('edit')->table($tile), ['settings' => ['html' => '']])
            ->assertHasFormErrors(['settings.html' => 'required']);
    }
}
