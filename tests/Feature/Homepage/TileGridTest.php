<?php

namespace Tests\Feature\Homepage;

use App\Filament\Resources\Sections\Pages\EditSection;
use App\Models\Section;
use App\Models\User;
use App\Services\Homepage\ContentIds;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TilesBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    protected function page(array $blocks): Section
    {
        return Section::factory()->create(['slug' => 'seite', 'content' => $blocks]);
    }

    /**
     * @param  list<array<string, mixed>>  $tiles
     * @return array{type: string, data: array<string, mixed>}
     */
    protected function tiles(array $tiles): array
    {
        return ['type' => 'tiles', 'data' => ['tiles' => $tiles]];
    }

    public function test_tiles_link_to_all_target_types(): void
    {
        $about = Section::factory()->create(['title' => 'Über uns', 'slug' => 'ueber-uns']);

        $this->page([$this->tiles([
            ['heading' => 'Bereich', 'target_type' => 'section', 'section_id' => $about->id, 'anchor' => 'team'],
            ['heading' => 'Kurse', 'target_type' => 'courses'],
            ['heading' => 'Kalender', 'target_type' => 'calendar'],
            ['heading' => 'Neuigkeiten', 'target_type' => 'news'],
            ['heading' => 'Extern', 'target_type' => 'url', 'url' => 'https://example.org/x'],
            ['heading' => 'Ohne Link', 'target_type' => 'none'],
        ])]);

        $html = $this->get('/seite')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('homepage.section', 'ueber-uns').'#team"', $html);
        $this->assertStringContainsString('href="'.route('homepage.courses.index').'"', $html);
        $this->assertStringContainsString('href="'.route('homepage.calendar').'"', $html);
        $this->assertStringContainsString('href="'.route('homepage.news.index').'"', $html);
        $this->assertStringContainsString('href="https://example.org/x"', $html);
        $this->assertStringContainsString('Ohne Link', $html);
    }

    public function test_empty_fields_fall_back_to_the_linked_section(): void
    {
        $about = Section::factory()->create([
            'title' => 'Über uns',
            'teaser' => 'Wer wir sind',
            'tile_image_path' => 'homepage/about.jpg',
            'tile_image_alt' => 'Unser Haus',
        ]);

        $this->page([$this->tiles([
            ['target_type' => 'section', 'section_id' => $about->id],
            ['target_type' => 'section', 'section_id' => $about->id, 'heading' => 'Eigene Überschrift', 'text' => '<p>Eigener <strong>Text</strong></p>'],
        ])]);

        $this->get('/seite')
            ->assertOk()
            ->assertSeeInOrder(['Über uns', 'Wer wir sind', 'Eigene Überschrift', 'Eigener'])
            ->assertSee('<strong>Text</strong>', false)
            ->assertSee('alt="Unser Haus"', false);
    }

    public function test_tiles_linking_to_hidden_or_missing_sections_are_left_out(): void
    {
        $hidden = Section::factory()->hidden()->create(['title' => 'Versteckt']);

        $this->page([$this->tiles([
            ['target_type' => 'section', 'section_id' => $hidden->id],
            ['target_type' => 'section', 'section_id' => 9999, 'heading' => 'Gelöscht'],
            ['heading' => 'Bleibt', 'target_type' => 'none'],
        ])]);

        $this->get('/seite')
            ->assertOk()
            ->assertDontSee('Versteckt')
            ->assertDontSee('Gelöscht')
            ->assertSee('Bleibt');
    }

    public function test_hero_tile_shows_eyebrow_heading_and_text(): void
    {
        $this->page([$this->tiles([[
            'style' => 'hero',
            'eyebrow' => 'Offiziell anerkannt',
            'heading' => 'Für eine Zukunft voller Chancen.',
            'text' => '<p>Alle Angebote sind kostenlos.</p>',
            'target_type' => 'none',
            'width' => 6,
        ]])]);

        $this->get('/seite')
            ->assertOk()
            ->assertSeeInOrder(['Offiziell anerkannt', 'Für eine Zukunft voller Chancen.', 'Alle Angebote sind kostenlos.'])
            ->assertSee('sm:text-5xl')
            ->assertSee('lg:col-span-6');
    }

    public function test_dangerous_links_and_markup_are_not_rendered(): void
    {
        $this->page([
            $this->tiles([[
                'heading' => 'Gefährlich',
                'text' => '<p>Hallo<script>alert(1)</script></p>',
                'target_type' => 'url',
                'url' => 'javascript:alert(2)',
            ]]),
            ['type' => 'button', 'data' => ['label' => 'Böser Button', 'target_type' => 'url', 'url' => 'javascript:alert(3)']],
        ]);

        $this->get('/seite')
            ->assertOk()
            ->assertSee('Gefährlich')
            ->assertDontSee('<script>alert(1)', false)
            ->assertDontSee('javascript:alert', false)
            ->assertDontSee('Böser Button');
    }

    public function test_button_block_links_to_its_target(): void
    {
        $team = Section::factory()->create(['slug' => 'das-team']);

        $this->page([['type' => 'button', 'data' => ['label' => 'Unser Team kennenlernen', 'target_type' => 'section', 'section_id' => $team->id]]]);

        $this->get('/seite')
            ->assertOk()
            ->assertSee('Unser Team kennenlernen')
            ->assertSee('href="'.route('homepage.section', 'das-team').'"', false);
    }

    public function test_saving_gives_blocks_and_tiles_unique_ids(): void
    {
        $page = $this->page([
            ['type' => 'text', 'data' => ['body' => '<p>A</p>', 'block_id' => 'gleich']],
            ['type' => 'text', 'data' => ['body' => '<p>B</p>', 'block_id' => 'gleich']],
            $this->tiles([['heading' => 'X', 'id' => 'kachel'], ['heading' => 'Y', 'id' => 'kachel']]),
        ]);

        $content = $page->fresh()->content;
        $ids = [
            $content[0]['data']['block_id'],
            $content[1]['data']['block_id'],
            $content[2]['data']['block_id'],
            $content[2]['data']['tiles'][0]['id'],
            $content[2]['data']['tiles'][1]['id'],
        ];

        $this->assertSame('gleich', $ids[0]);
        $this->assertSame('kachel', $ids[3]);
        $this->assertCount(5, array_unique($ids));
        $this->assertSame(2, ContentIds::blockIndex($content, $ids[2]));
        $this->assertNull(ContentIds::blockIndex($content, 'unbekannt'));
    }

    public function test_home_is_created_once_with_hero_and_root_sections(): void
    {
        $about = Section::factory()->create(['title' => 'Über uns']);
        Section::factory()->childOf($about)->create(['title' => 'Kind']);
        Section::factory()->standalone()->create(['title' => 'Impressum']);

        $home = Section::home();

        $this->get('/')->assertOk()->assertSee('Über uns')->assertDontSee('Kind');

        $this->assertTrue($home->is(Section::home()));
        $this->assertSame('tiles', $home->content[0]['type']);
        $this->assertSame('hero', $home->content[0]['data']['tiles'][0]['style']);
        $this->assertSame([$about->id], array_column($home->content[1]['data']['tiles'], 'section_id'));
        $this->assertSame(1, Section::query()->where('is_home', true)->count());
    }

    public function test_only_one_section_is_the_home(): void
    {
        $old = Section::factory()->create(['is_home' => true]);
        $new = Section::factory()->create(['is_home' => true]);

        $this->assertFalse($old->fresh()->is_home);
        $this->assertTrue($new->fresh()->is_home);
    }

    public function test_tiles_can_be_edited_in_the_cockpit_and_home_cannot_be_deleted(): void
    {
        $user = User::factory()->create();

        foreach (['ViewAny', 'View', 'Update', 'Delete', 'DeleteAny'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:Section"));
        }

        $this->actingAs($user);

        $about = Section::factory()->create(['title' => 'Über uns']);
        $home = Section::home();

        Livewire::test(EditSection::class, ['record' => $home->getRouteKey()])
            ->assertFormFieldHidden('slug')
            ->assertFormFieldHidden('parent_id')
            ->assertActionHidden(DeleteAction::class)
            ->fillForm(['content' => [
                ['type' => 'tiles', 'data' => ['tiles' => [
                    ['style' => 'standard', 'heading' => 'Neu im Cockpit', 'target_type' => 'section', 'section_id' => $about->id, 'width' => 3, 'height' => 1],
                ]]],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $tile = $home->fresh()->content[0]['data']['tiles'][0];

        $this->assertSame('Neu im Cockpit', $tile['heading']);
        $this->assertSame(3, (int) $tile['width']);
        $this->assertNotEmpty($tile['id']);

        $this->get('/')->assertSee('Neu im Cockpit');

        $this->assertFalse($user->can('delete', $home));
    }
}
