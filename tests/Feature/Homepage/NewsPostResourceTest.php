<?php

namespace Tests\Feature\Homepage;

use App\Filament\Resources\NewsPosts\Pages\CreateNewsPost;
use App\Filament\Resources\NewsPosts\Pages\EditNewsPost;
use App\Filament\Resources\NewsPosts\Pages\ListNewsPosts;
use App\Models\NewsPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NewsPostResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $user = User::factory()->create();

        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:NewsPost"));
        }

        $this->actingAs($user);
    }

    public function test_pages_render(): void
    {
        $post = NewsPost::factory()->create();

        $this->get('/cockpit/news-posts')->assertOk();
        $this->get('/cockpit/news-posts/create')->assertOk();
        $this->get("/cockpit/news-posts/{$post->id}/edit")->assertOk();
    }

    public function test_user_without_permission_cannot_access(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/cockpit/news-posts')->assertForbidden();
    }

    public function test_list_shows_posts_and_filters_by_status(): void
    {
        $published = NewsPost::factory()->create();
        $draft = NewsPost::factory()->draft()->create();
        $scheduled = NewsPost::factory()->scheduled()->create();

        Livewire::test(ListNewsPosts::class)
            ->assertCanSeeTableRecords([$published, $draft, $scheduled])
            ->filterTable('status', 'draft')
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$published, $scheduled]);
    }

    public function test_can_create_post_with_generated_slug(): void
    {
        Livewire::test(CreateNewsPost::class)
            ->fillForm(['title' => 'Bürgerpreis für das Z-LAB'])
            ->assertSchemaStateSet(['slug' => 'buergerpreis-fuer-das-z-lab'])
            ->fillForm([
                'teaser' => 'Wir freuen uns riesig.',
                'published_at' => '2026-11-01 10:00',
                'content' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(NewsPost::class, [
            'title' => 'Bürgerpreis für das Z-LAB',
            'slug' => 'buergerpreis-fuer-das-z-lab',
            'teaser' => 'Wir freuen uns riesig.',
        ]);
    }

    public function test_can_save_post_as_draft(): void
    {
        $post = NewsPost::factory()->create();

        Livewire::test(EditNewsPost::class, ['record' => $post->getRouteKey()])
            ->fillForm(['published_at' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($post->refresh()->published_at);
    }

    public function test_slug_must_be_unique_and_title_is_required(): void
    {
        NewsPost::factory()->create(['slug' => 'vergeben']);

        Livewire::test(CreateNewsPost::class)
            ->fillForm(['title' => null, 'slug' => 'vergeben'])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required', 'slug' => 'unique']);
    }
}
