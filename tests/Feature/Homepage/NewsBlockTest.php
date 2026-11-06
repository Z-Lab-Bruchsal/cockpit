<?php

namespace Tests\Feature\Homepage;

use App\Models\NewsPost;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function newsSection(array $data = []): Section
    {
        return Section::factory()->create([
            'slug' => 'aktuelles',
            'content' => [['type' => 'news', 'data' => $data]],
        ]);
    }

    public function test_block_shows_latest_published_posts_up_to_the_limit(): void
    {
        NewsPost::factory()->create(['title' => 'Ganz alter Beitrag', 'published_at' => now()->subDays(10)]);
        NewsPost::factory()->create(['title' => 'Mittlerer Beitrag', 'published_at' => now()->subDays(5)]);
        NewsPost::factory()->create(['title' => 'Neuester Beitrag', 'teaser' => 'Drohnen im Schlossgarten', 'published_at' => now()->subDay()]);
        NewsPost::factory()->draft()->create(['title' => 'Entwurf']);
        NewsPost::factory()->scheduled()->create(['title' => 'Geplant']);
        $this->newsSection(['limit' => 2]);

        $this->get('/aktuelles')
            ->assertOk()
            ->assertSeeInOrder(['Neuester Beitrag', 'Drohnen im Schlossgarten', 'Mittlerer Beitrag'])
            ->assertDontSee('Ganz alter Beitrag')
            ->assertDontSee('Entwurf')
            ->assertDontSee('Geplant');
    }

    public function test_block_links_to_all_news_unless_disabled(): void
    {
        $this->newsSection();

        $this->get('/aktuelles')
            ->assertOk()
            ->assertSee('Aktuell gibt es noch keine Neuigkeiten.')
            ->assertSee('Alle News ansehen');

        Section::query()->delete();
        $this->newsSection(['show_all_link' => false]);

        $this->get('/aktuelles')
            ->assertOk()
            ->assertDontSee('Alle News ansehen');
    }

    public function test_limit_is_clamped(): void
    {
        NewsPost::factory()->count(14)->create(['published_at' => now()->subDay()]);
        $this->newsSection(['limit' => 500]);

        $response = $this->get('/aktuelles')->assertOk();

        $this->assertSame(12, substr_count($response->getContent(), 'Weiterlesen'));
    }

    public function test_block_can_be_linked_via_anchor(): void
    {
        $this->newsSection(['anchor' => 'neuigkeiten']);

        $this->get('/aktuelles')
            ->assertOk()
            ->assertSee('id="neuigkeiten"', false);
    }
}
