<?php

namespace Tests\Feature\Homepage;

use App\Models\NewsPost;
use Database\Seeders\NewsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Http::preventStrayRequests();
        Storage::fake('public');
    }

    public function test_seeds_current_news_with_downloaded_images_and_is_repeatable(): void
    {
        Http::fake(['www.auerbach-stiftung.de/img/uploads/*' => Http::response('fake-image', 200, ['Content-Type' => 'image/webp'])]);

        $this->seed(NewsSeeder::class);
        $this->seed(NewsSeeder::class);

        $this->assertSame(6, NewsPost::query()->count());
        $this->assertSame(
            'drone-soccer-school-cup-2026',
            NewsPost::query()->published()->get()->last()->slug,
        );

        $post = NewsPost::query()->where('slug', 'neura-robotics-zu-gast-im-z-lab')->firstOrFail();
        $this->assertSame('homepage/news/neura-robotics-presentation_293_1793719341.webp', $post->image_path);
        Storage::disk('public')->assertExists($post->image_path);
        $this->assertContains('image', array_column($post->content, 'type'));

        // Images already on disk are not downloaded a second time.
        Http::assertSentCount(15);

        $this->get('/news')
            ->assertOk()
            ->assertSeeInOrder(['Unser Jahresrückblick 2025/26', 'NEURA Robotics zu Gast im Z-LAB', 'Bürgerpreis 2026 für Alexander Gabriel']);
        $this->get($post->url())->assertOk()->assertSee('15 Minuten für unsere jungen Technikbegeisterten');
    }

    public function test_posts_are_created_without_images_if_downloads_fail(): void
    {
        Http::fake(['*' => Http::response('Not found', 404, ['Content-Type' => 'text/html'])]);

        $this->seed(NewsSeeder::class);

        $post = NewsPost::query()->where('slug', 'drone-soccer-team-bei-enbw')->firstOrFail();
        $this->assertNull($post->image_path);
        $this->assertNull($post->image_alt);
        $this->assertNotContains('image', array_column($post->content, 'type'));
        $this->get($post->url())->assertOk()->assertSee('Rheinhafen-Dampfkraftwerk');
    }
}
