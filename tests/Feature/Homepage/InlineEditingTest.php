<?php

namespace Tests\Feature\Homepage;

use App\Enums\CourseProminence;
use App\Filament\Pages\Inline\EditBlockInline;
use App\Filament\Pages\Inline\EditOfferInline;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InlineEditingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @param  list<string>  $models
     */
    protected function editor(array $models = ['Section', 'CourseInstance']): User
    {
        $user = User::factory()->create();

        foreach ($models as $model) {
            foreach (['ViewAny', 'View', 'Update'] as $ability) {
                $user->givePermissionTo(Permission::findOrCreate("{$ability}:{$model}"));
            }
        }

        return $user;
    }

    protected function page(): Section
    {
        return Section::factory()->create([
            'slug' => 'seite',
            'title' => 'Meine Seite',
            'content' => [
                ['type' => 'text', 'data' => ['body' => '<p>Erster Block</p>']],
                ['type' => 'text', 'data' => ['body' => '<p>Zweiter Block</p>']],
                ['type' => 'offers', 'data' => ['show_filter_tabs' => false]],
            ],
        ]);
    }

    protected function blockId(Section $section, int $index): string
    {
        return $section->fresh()->content[$index]['data']['block_id'];
    }

    public function test_guests_and_users_without_permission_get_no_edit_mode(): void
    {
        $this->page();

        $this->get('/seite?bearbeiten=1')->assertOk()->assertDontSee('Block bearbeiten')->assertDontSee('Bearbeiten-Modus');

        $this->actingAs(User::factory()->create())
            ->get('/seite?bearbeiten=1')
            ->assertOk()
            ->assertDontSee('Block bearbeiten')
            ->assertDontSee('>Bearbeiten<', false);
    }

    public function test_editor_switches_on_edit_mode_and_gets_a_pencil_per_block_and_offer(): void
    {
        $page = $this->page();
        $instance = CourseInstance::factory()->withDates()->create(['title' => 'Löten']);

        $this->actingAs($this->editor());

        $this->get('/seite')->assertOk()->assertSee('>Bearbeiten<', false)->assertDontSee('Block bearbeiten');

        $this->get('/seite?bearbeiten=1')
            ->assertOk()
            ->assertSee('Bearbeiten-Modus')
            ->assertSee(EditBlockInline::getUrl(['section' => $page->id, 'block' => $this->blockId($page, 1)]))
            ->assertSee(EditOfferInline::getUrl(['instance' => $instance->id]))
            ->assertSee('Angebot bearbeiten');

        $this->get($instance->url().'?bearbeiten=1')
            ->assertOk()
            ->assertSee(EditOfferInline::getUrl(['instance' => $instance->id]));
    }

    public function test_users_who_may_only_edit_offers_get_no_block_pencils(): void
    {
        $this->page();
        CourseInstance::factory()->withDates()->create();

        $this->actingAs($this->editor(['CourseInstance']))
            ->get('/seite?bearbeiten=1')
            ->assertOk()
            ->assertDontSee('Block bearbeiten')
            ->assertSee('Angebot bearbeiten');
    }

    public function test_block_editor_saves_only_its_block(): void
    {
        $page = $this->page();
        $blockId = $this->blockId($page, 1);

        $this->actingAs($this->editor());

        $this->get(EditBlockInline::getUrl(['section' => $page->id, 'block' => $blockId]))
            ->assertOk()
            ->assertSee('Text bearbeiten')
            ->assertDontSee('fi-sidebar', false);

        Livewire::withQueryParams(['section' => $page->id, 'block' => $blockId])
            ->test(EditBlockInline::class)
            ->assertSee('Seite „Meine Seite“')
            ->assertSchemaStateSet(['body' => '<p>Zweiter Block</p>'])
            ->fillForm(['body' => '<p>Geändert</p>'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Gespeichert');

        $content = $page->fresh()->content;

        $this->assertSame('<p>Erster Block</p>', $content[0]['data']['body']);
        $this->assertStringContainsString('Geändert', $content[1]['data']['body']);
        $this->assertSame($blockId, $content[1]['data']['block_id']);
        $this->assertSame('offers', $content[2]['type']);
    }

    public function test_block_editor_rejects_unknown_blocks_and_missing_permission(): void
    {
        $page = $this->page();

        $this->actingAs($this->editor());

        Livewire::withQueryParams(['section' => $page->id, 'block' => 'gibt-es-nicht'])
            ->test(EditBlockInline::class)
            ->assertNotFound();

        $this->actingAs($this->editor(['CourseInstance']));

        Livewire::withQueryParams(['section' => $page->id, 'block' => $this->blockId($page, 0)])
            ->test(EditBlockInline::class)
            ->assertForbidden();
    }

    public function test_offer_editor_changes_only_the_instance(): void
    {
        $course = Course::factory()->create(['name' => 'Löten für Anfänger', 'teaser' => 'Kurstext']);
        $instance = CourseInstance::factory()->for($course)->withDates()->create();
        $sibling = CourseInstance::factory()->for($course)->withDates()->create();

        $this->actingAs($this->editor());

        $this->get(EditOfferInline::getUrl(['instance' => $instance->id]))->assertOk();

        Livewire::withQueryParams(['instance' => $instance->id])
            ->test(EditOfferInline::class)
            ->assertSee('Du bearbeitest nur die Durchführung')
            ->assertSee('Kurs-Stammdaten und andere Durchführungen')
            ->fillForm([
                'title' => 'Löten im Herbst',
                'teaser' => 'Nur für diese Durchführung',
                'prominence' => CourseProminence::Featured->value,
                'card_label' => 'Löten lernen.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $instance->refresh();

        $this->assertSame('Löten im Herbst', $instance->title);
        $this->assertSame('Nur für diese Durchführung', $instance->teaser);
        $this->assertTrue($instance->isFeatured());
        $this->assertSame('Löten lernen.', $instance->card_label);
        $this->assertSame('Löten für Anfänger', $course->fresh()->name);
        $this->assertSame('Kurstext', $course->fresh()->teaser);
        $this->assertNull($sibling->fresh()->teaser);
    }

    public function test_offer_editor_needs_update_permission(): void
    {
        $instance = CourseInstance::factory()->withDates()->create();

        $this->actingAs($this->editor(['Section']));

        Livewire::withQueryParams(['instance' => $instance->id])
            ->test(EditOfferInline::class)
            ->assertForbidden();
    }
}
