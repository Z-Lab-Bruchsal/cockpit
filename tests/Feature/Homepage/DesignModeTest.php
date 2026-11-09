<?php

namespace Tests\Feature\Homepage;

use App\Filament\Pages\DesignPage;
use App\Livewire\Homepage\DesignEditor;
use App\Models\CompanySetting;
use App\Models\DesignTheme;
use App\Models\DesignThemeRevision;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DesignModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        CompanySetting::current()->update(['primary_color' => '#e41f6c', 'secondary_color' => '#fff7ed']);
    }

    protected function designer(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('Homepage:Design'));

        return $user;
    }

    /**
     * A page with one tile, returning the tile id.
     */
    protected function pageWithTile(): string
    {
        $section = Section::factory()->create([
            'slug' => 'seite',
            'content' => [['type' => 'tiles', 'data' => ['tiles' => [['heading' => 'Kachel', 'target_type' => 'none']]]]],
        ]);

        return $section->content[0]['data']['tiles'][0]['id'];
    }

    public function test_published_roles_and_element_overrides_are_rendered(): void
    {
        $tileId = $this->pageWithTile();

        DesignTheme::current()->update([
            'tokens' => ['footer-bg' => '#111111', 'unknown' => '#222222', 'ink' => 'red;evil'],
            'element_overrides' => ['tile:'.$tileId => ['card-bg' => '#00ff00', 'brand' => '#0000ff']],
        ]);

        $this->get('/seite')
            ->assertOk()
            ->assertSee('--footer-bg: #111111', false)
            ->assertSee('--brand: #e41f6c', false)
            ->assertSee('--accent: #fff7ed', false)
            ->assertDontSee('#222222', false)
            ->assertDontSee('red;evil', false)
            ->assertSee('data-design-element="tile:'.$tileId.'"', false)
            ->assertSee('style="--card-bg: #00ff00; --brand: #0000ff; --brand-contrast: #ffffff', false);
    }

    public function test_design_mode_is_only_available_with_permission(): void
    {
        $this->pageWithTile();

        $this->get('/seite?design=1')->assertOk()->assertDontSeeLivewire(DesignEditor::class)->assertDontSee('Farben gestalten');

        $this->actingAs(User::factory()->create())
            ->get('/seite?design=1')
            ->assertOk()
            ->assertDontSeeLivewire(DesignEditor::class);

        $this->actingAs($this->designer());

        $this->get('/seite')->assertOk()->assertSee('Farben gestalten')->assertDontSeeLivewire(DesignEditor::class);
        $this->get('/seite?design=1')->assertOk()->assertSee('Design-Modus')->assertSeeLivewire(DesignEditor::class);
    }

    public function test_draft_is_only_visible_in_design_mode(): void
    {
        $designer = $this->designer();
        DesignTheme::current()->saveDraft(['tokens' => ['footer-bg' => '#abcdef']], $designer);

        $this->get('/')->assertOk()->assertDontSee('#abcdef', false);

        $this->actingAs($designer);

        $this->get('/')->assertOk()->assertDontSee('#abcdef', false);
        $this->get('/?design=1')->assertOk()->assertSee('--footer-bg: #abcdef', false);
    }

    public function test_editor_saves_a_sanitized_draft(): void
    {
        $this->actingAs($this->designer());

        Livewire::test(DesignEditor::class)
            ->call('saveDraft', [
                'tokens' => ['tile-heading' => '#ABCDEF', 'nope' => '#000000', 'ink' => 'url(evil)'],
                'elements' => [
                    'tile:abc-123' => ['card-bg' => '#ffeedd', 'ink' => 'nope'],
                    'script:<x>' => ['card-bg' => '#ffeedd'],
                ],
            ])
            ->assertSet('hasDraft', true);

        $theme = DesignTheme::current();

        $this->assertSame(['tile-heading' => '#abcdef'], $theme->draft['tokens']);
        $this->assertSame(['tile:abc-123' => ['card-bg' => '#ffeedd']], $theme->draft['elements']);
        $this->assertNull($theme->tokens);
    }

    public function test_publishing_stores_roles_brand_color_and_a_revision(): void
    {
        $designer = $this->designer();
        $this->actingAs($designer);

        Livewire::test(DesignEditor::class)
            ->call('publish', [
                'tokens' => ['brand' => '#1d4ed8', 'footer-bg' => '#000000'],
                'elements' => ['offer:5' => ['card-bg' => '#ffffff']],
            ])
            ->assertDispatched('design-reload');

        $theme = DesignTheme::current();

        $this->assertSame(['footer-bg' => '#000000'], $theme->tokens);
        $this->assertSame(['offer:5' => ['card-bg' => '#ffffff']], $theme->element_overrides);
        $this->assertNull($theme->draft);
        $this->assertSame('#1d4ed8', CompanySetting::current()->primary_color);
        $this->assertSame('#fff7ed', CompanySetting::current()->secondary_color);

        $revision = DesignThemeRevision::query()->sole();
        $this->assertSame('#e41f6c', $revision->snapshot['tokens']['brand']);
        $this->assertSame($designer->id, $revision->user_id);

        $theme->restore($revision, $designer);

        $this->assertSame('#e41f6c', CompanySetting::current()->primary_color);
        $this->assertSame([], DesignTheme::current()->tokens);
        $this->assertSame(2, DesignThemeRevision::query()->count());
    }

    public function test_discarding_removes_the_draft(): void
    {
        $designer = $this->designer();
        DesignTheme::current()->saveDraft(['tokens' => ['ink' => '#000000']], $designer);

        $this->actingAs($designer);

        Livewire::test(DesignEditor::class)->call('discard')->assertDispatched('design-reload');

        $this->assertNull(DesignTheme::current()->draft);
    }

    public function test_editor_is_forbidden_without_permission(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(DesignEditor::class)->assertForbidden();
    }

    public function test_cockpit_page_publishes_colors_and_element_overrides(): void
    {
        $designer = $this->designer();
        DesignTheme::current()->update(['element_overrides' => ['tile:a' => ['card-bg' => '#eeeeee'], 'tile:b' => ['card-bg' => '#dddddd']]]);

        $this->actingAs($designer);

        $this->get(DesignPage::getUrl())->assertOk();

        Livewire::test(DesignPage::class)
            ->assertSchemaStateSet(['tokens.brand' => '#e41f6c'])
            ->fillForm([
                'tokens' => ['brand' => '#123456', 'accent' => '#fff7ed', 'footer-hover' => '#ff0000'],
                'elements' => [['element' => 'tile:b', 'role' => 'card-bg', 'color' => '#cccccc']],
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Farben veröffentlicht');

        $this->assertSame('#123456', CompanySetting::current()->primary_color);
        $this->assertSame(['footer-hover' => '#ff0000'], DesignTheme::current()->tokens);
        $this->assertSame(['tile:b' => ['card-bg' => '#cccccc']], DesignTheme::current()->element_overrides);

        Livewire::test(DesignPage::class)
            ->fillForm(['tokens' => ['brand' => 'blau']])
            ->call('save')
            ->assertHasFormErrors(['tokens.brand' => 'regex']);
    }

    public function test_cockpit_page_restores_a_previous_version(): void
    {
        $designer = $this->designer();
        $this->actingAs($designer);

        DesignTheme::current()->publish($designer, ['tokens' => ['brand' => '#000000', 'accent' => '#ffffff']]);
        $revision = DesignThemeRevision::query()->sole();

        Livewire::test(DesignPage::class)
            ->callAction('restore', ['revision' => $revision->id])
            ->assertNotified('Version wiederhergestellt')
            ->assertSchemaStateSet(['tokens.brand' => '#e41f6c']);

        $this->assertSame('#e41f6c', CompanySetting::current()->primary_color);
    }

    public function test_cockpit_page_needs_permission_and_boss_has_it(): void
    {
        $this->actingAs(User::factory()->create())->get(DesignPage::getUrl())->assertForbidden();

        $this->seed(ShieldSeeder::class);
        $boss = User::factory()->create();
        $boss->assignRole('Z-Lab-Boss');

        $this->actingAs($boss)->get(DesignPage::getUrl())->assertOk();
    }
}
