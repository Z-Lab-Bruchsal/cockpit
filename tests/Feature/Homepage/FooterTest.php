<?php

namespace Tests\Feature\Homepage;

use App\Filament\Clusters\Settings\Pages\FooterPage;
use App\Filament\Pages\Inline\EditFooterBlockInline;
use App\Filament\Pages\Inline\EditFooterLinkInline;
use App\Livewire\Homepage\FooterEditor;
use App\Models\CompanySetting;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FooterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ShieldSeeder::class);

        CompanySetting::current()->update([
            'name' => 'Z-LAB',
            'street' => 'Werner-von-Siemens-Str. 2',
            'social_links' => [['platform' => 'instagram', 'url' => 'https://instagram.com/zlab']],
        ]);
    }

    protected function boss(): User
    {
        $boss = User::factory()->create();
        $boss->assignRole('Z-Lab-Boss');

        return $boss;
    }

    /**
     * @return list<string>
     */
    protected function footerTypes(): array
    {
        return array_column(CompanySetting::current()->footerBlocks(), 'type');
    }

    protected function footerId(int $index): string
    {
        return CompanySetting::current()->footerBlocks()[$index]['data']['block_id'];
    }

    public function test_default_footer_shows_address_links_and_social_media(): void
    {
        $this->assertSame(['address', 'links', 'social'], $this->footerTypes());

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Werner-von-Siemens-Str. 2', 'Instagram']);
    }

    public function test_text_elements_are_rendered_in_the_saved_order_and_sanitized(): void
    {
        CompanySetting::current()->update(['footer_blocks' => [
            ['type' => 'social', 'data' => ['width' => 2]],
            ['type' => 'text', 'data' => ['body' => '<p><strong>Gefördert</strong> von der Stiftung<script>alert(1)</script></p>', 'width' => 4]],
            ['type' => 'unknown', 'data' => []],
        ]]);

        $this->assertSame(['social', 'text'], $this->footerTypes());
        $this->assertNotEmpty($this->footerId(1));

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Instagram', 'Gefördert'])
            ->assertSee('lg:col-span-4')
            ->assertDontSee('<script>alert(1)', false)
            ->assertDontSee('Werner-von-Siemens-Str. 2');
    }

    public function test_boss_arranges_the_footer_on_the_homepage(): void
    {
        $this->actingAs($this->boss());

        $this->get('/')->assertOk()->assertSee('>Bearbeiten<', false)->assertDontSeeLivewire(FooterEditor::class);
        $this->get('/news?bearbeiten=1')->assertOk()->assertSee('Bearbeiten-Modus')->assertSeeLivewire(FooterEditor::class);
        $this->get('/news?anordnen=1')->assertOk()->assertSeeLivewire(FooterEditor::class);

        $socialId = $this->footerId(2);

        Livewire::test(FooterEditor::class)
            ->call('sort', $socialId, 0)
            ->call('resize', $socialId, 6);

        $this->assertSame(['social', 'address', 'links'], $this->footerTypes());
        $this->assertSame(6, CompanySetting::current()->footerBlocks()[0]['data']['width']);

        Livewire::test(FooterEditor::class)
            ->call('resize', $socialId, 5)
            ->assertHasErrors('width');

        Livewire::test(FooterEditor::class)
            ->call('addText')
            ->assertDispatched('open-inline-editor');

        $this->assertSame(['social', 'address', 'links', 'text'], $this->footerTypes());

        Livewire::test(FooterEditor::class)->call('remove', $this->footerId(1));

        $this->assertSame(['social', 'links', 'text'], $this->footerTypes());
    }

    public function test_links_and_profiles_are_sorted_removed_and_edited_in_place(): void
    {
        CompanySetting::current()->update([
            'footer_links' => [
                ['label' => 'Impressum', 'section_id' => null, 'url' => 'https://example.org/impressum'],
                ['label' => 'Datenschutz', 'section_id' => null, 'url' => 'https://example.org/datenschutz'],
                ['label' => 'Kontakt', 'section_id' => null, 'url' => 'https://example.org/kontakt'],
            ],
            'social_links' => [
                ['platform' => 'instagram', 'url' => 'https://instagram.com/zlab'],
                ['platform' => 'youtube', 'url' => 'https://youtube.com/@zlab'],
            ],
        ]);

        $this->actingAs($this->boss());

        $this->get('/?bearbeiten=1')
            ->assertOk()
            ->assertSee('data-footer-entries="links"', false)
            ->assertSee(EditFooterLinkInline::getUrl(['kind' => 'social', 'index' => 1]));

        Livewire::test(FooterEditor::class)
            ->call('sortLinks', '2', 0)
            ->call('sortSocial', '1', 0)
            ->call('removeEntry', 'links', 1);

        $settings = CompanySetting::current();
        $this->assertSame(['Kontakt', 'Datenschutz'], array_column($settings->footer_links, 'label'));
        $this->assertSame(['youtube', 'instagram'], array_column($settings->social_links, 'platform'));

        Livewire::test(FooterEditor::class)
            ->call('addEntry', 'social')
            ->assertDispatched('open-inline-editor');

        Livewire::test(FooterEditor::class)->call('removeEntry', 'nope', 0)->assertNotFound();
        Livewire::test(FooterEditor::class)->call('removeEntry', 'links', 9)->assertNotFound();

        Livewire::withQueryParams(['kind' => 'links', 'index' => '0'])
            ->test(EditFooterLinkInline::class)
            ->assertSee('Footer-Link bearbeiten')
            ->assertSchemaStateSet(['label' => 'Kontakt'])
            ->fillForm(['label' => 'Kontakt & Anfahrt', 'url' => 'https://example.org/anfahrt'])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::withQueryParams(['kind' => 'social', 'index' => 'neu'])
            ->test(EditFooterLinkInline::class)
            ->assertSee('Social-Media-Profil hinzufügen')
            ->fillForm(['platform' => 'mastodon', 'url' => 'javascript:alert(1)'])
            ->call('save')
            ->assertHasFormErrors(['url'])
            ->fillForm(['url' => 'https://mastodon.social/@zlab'])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = CompanySetting::current();
        $this->assertSame('Kontakt & Anfahrt', $settings->footer_links[0]['label']);
        $this->assertSame('https://example.org/anfahrt', $settings->footer_links[0]['url']);
        $this->assertSame(['youtube', 'instagram', 'mastodon'], array_column($settings->social_links, 'platform'));

        $this->get('/')->assertSeeInOrder(['Kontakt &amp; Anfahrt', 'Datenschutz', 'YouTube', 'Instagram', 'Mastodon'], false);

        Livewire::withQueryParams(['kind' => 'links', 'index' => '7'])
            ->test(EditFooterLinkInline::class)
            ->assertNotFound();
    }

    public function test_footer_editor_needs_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/?bearbeiten=1')->assertOk()->assertDontSeeLivewire(FooterEditor::class)->assertDontSee('Bearbeiten-Modus');
        Livewire::test(FooterEditor::class)->assertForbidden();
        $this->get(EditFooterLinkInline::getUrl(['kind' => 'links', 'index' => 'neu']))->assertForbidden();
    }

    public function test_text_element_is_edited_in_the_dialog(): void
    {
        CompanySetting::current()->update(['footer_blocks' => [
            ['type' => 'address', 'data' => ['width' => 2]],
            ['type' => 'text', 'data' => ['body' => '<p>Alt</p>', 'width' => 2]],
        ]]);
        $textId = $this->footerId(1);

        $this->actingAs($this->boss());

        $this->get(EditFooterBlockInline::getUrl(['block' => $textId]))->assertOk();

        Livewire::withQueryParams(['block' => $textId])
            ->test(EditFooterBlockInline::class)
            ->assertSchemaStateSet(['body' => '<p>Alt</p>'])
            ->fillForm(['body' => '<p>Neu im Footer</p>', 'width' => 3])
            ->call('save')
            ->assertHasNoFormErrors();

        $block = CompanySetting::current()->footerBlocks()[1];
        $this->assertStringContainsString('Neu im Footer', $block['data']['body']);
        $this->assertSame(3, (int) $block['data']['width']);
        $this->assertSame($textId, $block['data']['block_id']);

        Livewire::withQueryParams(['block' => $this->footerId(0)])
            ->test(EditFooterBlockInline::class)
            ->assertNotFound();
    }

    public function test_footer_is_configured_in_the_company_settings(): void
    {
        $this->actingAs($this->boss());

        Livewire::test(FooterPage::class)
            ->assertSchemaStateSet(fn (array $state): array => [
                'footer_blocks' => $state['footer_blocks'],
            ])
            ->fillForm(['footer_blocks' => [
                ['type' => 'text', 'data' => ['body' => '<p>Im Cockpit</p>', 'width' => 6]],
                ['type' => 'links', 'data' => ['width' => 2]],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['text', 'links'], $this->footerTypes());
        $this->get('/')->assertSee('Im Cockpit');
    }
}
