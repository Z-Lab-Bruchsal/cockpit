<?php

namespace Tests\Feature\Homepage;

use App\Models\NewsPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_index_renders_without_posts(): void
    {
        $this->get('/news')
            ->assertOk()
            ->assertSee('Aktuell gibt es noch keine Neuigkeiten.');
    }

    public function test_index_lists_published_posts_newest_first(): void
    {
        NewsPost::factory()->create(['title' => 'Älterer Beitrag', 'published_at' => now()->subDays(5)]);
        NewsPost::factory()->create(['title' => 'Neuer Beitrag', 'teaser' => 'Kurz und knapp', 'published_at' => now()->subDay()]);

        $this->get('/news')
            ->assertOk()
            ->assertSeeInOrder(['Neuer Beitrag', 'Kurz und knapp', 'Älterer Beitrag']);
    }

    public function test_index_hides_drafts_and_scheduled_posts(): void
    {
        NewsPost::factory()->create(['title' => 'Öffentlich']);
        NewsPost::factory()->draft()->create(['title' => 'Entwurfsbeitrag']);
        NewsPost::factory()->scheduled()->create(['title' => 'Geplanter Beitrag']);

        $this->get('/news')
            ->assertOk()
            ->assertSee('Öffentlich')
            ->assertDontSee('Entwurfsbeitrag')
            ->assertDontSee('Geplanter Beitrag');
    }

    public function test_index_is_paginated_by_six(): void
    {
        foreach (range(1, 7) as $day) {
            NewsPost::factory()->create(['title' => "Beitrag Nummer {$day}x", 'published_at' => now()->subDays($day)]);
        }

        $this->get('/news')
            ->assertOk()
            ->assertSee('Beitrag Nummer 6x')
            ->assertDontSee('Beitrag Nummer 7x')
            ->assertSee('Seite 1 von 2');

        $this->get('/news?page=2')
            ->assertOk()
            ->assertSee('Beitrag Nummer 7x')
            ->assertDontSee('Beitrag Nummer 1x');
    }

    public function test_show_renders_published_post_with_content_blocks(): void
    {
        $post = NewsPost::factory()->create([
            'title' => 'Drone Soccer Cup',
            'slug' => 'drone-soccer-cup',
            'published_at' => now()->setDate(2026, 7, 18),
            'content' => [['type' => 'text', 'data' => ['body' => '<p>Was für ein Turniertag!</p>']]],
        ]);

        $this->get($post->url())
            ->assertOk()
            ->assertSee('Drone Soccer Cup')
            ->assertSee('18.07.2026')
            ->assertSee('Was für ein Turniertag!');
    }

    public function test_show_returns_404_for_drafts_scheduled_and_unknown_posts(): void
    {
        $draft = NewsPost::factory()->draft()->create();
        $scheduled = NewsPost::factory()->scheduled()->create();

        $this->get("/news/{$draft->slug}")->assertNotFound();
        $this->get("/news/{$scheduled->slug}")->assertNotFound();
        $this->get('/news/gibt-es-nicht')->assertNotFound();
    }

    public function test_navigation_and_head_link_to_news_and_feed(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('href="'.route('homepage.news.index').'"', false)
            ->assertSee('type="application/rss+xml"', false)
            ->assertSee('href="'.route('feeds.main').'"', false);
    }

    public function test_feed_contains_only_published_posts(): void
    {
        $post = NewsPost::factory()->create(['title' => 'Im Feed', 'teaser' => 'Feed-Kurztext']);
        NewsPost::factory()->draft()->create(['title' => 'Nicht im Feed']);
        NewsPost::factory()->scheduled()->create(['title' => 'Noch nicht im Feed']);

        $response = $this->get('/news/feed');

        $response->assertOk();
        $this->assertStringContainsString('xml', (string) $response->headers->get('Content-Type'));
        $response->assertSee('<rss', false)
            ->assertSee('Im Feed')
            ->assertSee('Feed-Kurztext')
            ->assertSee($post->url(), false)
            ->assertDontSee('Nicht im Feed')
            ->assertDontSee('Noch nicht im Feed');
    }

    public function test_feed_renders_without_posts(): void
    {
        $this->get('/news/feed')
            ->assertOk()
            ->assertSee('<rss', false);
    }
}
