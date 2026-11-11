<?php

namespace Tests\Feature\Homepage;

use App\Services\Homepage\LegacyContentConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LegacyContentConverterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array<string, mixed>>
     */
    protected function legacyContent(string $tileId, string $blockId): array
    {
        return [
            ['type' => 'tiles', 'data' => ['block_id' => (string) Str::uuid(), 'tiles' => [
                ['id' => $tileId, 'style' => 'hero', 'eyebrow' => 'Dach', 'heading' => 'Willkommen', 'text' => '<p>Hallo</p>', 'target_type' => 'none', 'width' => 6, 'height' => 1],
                ['id' => (string) Str::uuid(), 'style' => 'standard', 'heading' => null, 'text' => '<p></p>', 'image' => null, 'target_type' => 'section', 'section_id' => '7', 'anchor' => 'team', 'width' => 3, 'height' => 2],
                ['id' => 'kein-uuid', 'style' => 'highlighted', 'heading' => 'Extern', 'target_type' => 'url', 'url' => 'https://example.org', 'image' => 'homepage/x.jpg', 'alt' => 'X', 'width' => 5],
            ]]],
            ['type' => 'text', 'data' => ['block_id' => $blockId, 'body' => '<p>Text</p>']],
            ['type' => 'course_list', 'data' => ['block_id' => (string) Str::uuid(), 'interest_ids' => ['3', '4'], 'audience' => 'kids', 'limit' => '4', 'show_all_link' => false]],
            ['type' => 'team', 'data' => ['block_id' => (string) Str::uuid(), 'areas' => ['leitung'], 'anchor' => 'team']],
            ['type' => 'unbekannt', 'data' => []],
            'kaputt',
        ];
    }

    public function test_blocks_become_tiles_in_order(): void
    {
        $tileId = (string) Str::uuid();
        $blockId = (string) Str::uuid();

        $rows = LegacyContentConverter::toTiles($this->legacyContent($tileId, $blockId));

        $this->assertSame(['content', 'section', 'content', 'text', 'courses', 'team'], array_column($rows, 'type'));
        $this->assertSame([1, 2, 3, 4, 5, 6], array_column($rows, 'sort'));

        [$hero, $sectionLink, $external, $text, $courses, $team] = $rows;

        $this->assertSame($tileId, $hero['uuid']);
        $this->assertSame(['hero', 'Dach', 'Willkommen', 6], [$hero['style'], $hero['eyebrow'], $hero['heading'], $hero['width']]);

        $this->assertSame(7, $sectionLink['link_section_id']);
        $this->assertSame('team', $sectionLink['link_anchor']);
        $this->assertNull($sectionLink['text']);
        $this->assertSame([3, 2], [$sectionLink['width'], $sectionLink['height']]);

        $this->assertTrue(Str::isUuid($external['uuid']));
        $this->assertSame(['url', 'https://example.org', 'homepage/x.jpg', 'X', 2], [$external['link_type'], $external['link_url'], $external['image_path'], $external['image_alt'], $external['width']]);

        $this->assertSame($blockId, $text['uuid']);
        $this->assertSame(['body' => '<p>Text</p>'], $text['settings']);
        $this->assertSame(6, $text['width']);

        $this->assertSame([3, 4], $courses['settings']['interest_ids']);
        $this->assertSame(['kids', 4, false, 2], [$courses['settings']['audience'], $courses['settings']['limit'], $courses['settings']['show_all_link'], $courses['settings']['item_width']]);

        $this->assertSame(['areas' => ['leitung'], 'anchor' => 'team'], $team['settings']);
    }

    public function test_tiles_convert_back_to_blocks(): void
    {
        $tileId = (string) Str::uuid();
        $blockId = (string) Str::uuid();

        $content = LegacyContentConverter::toContent(LegacyContentConverter::toTiles($this->legacyContent($tileId, $blockId)));

        $this->assertSame(['tiles', 'text', 'course_list', 'team'], array_column($content, 'type'));
        $this->assertCount(3, $content[0]['data']['tiles']);
        $this->assertSame($tileId, $content[0]['data']['tiles'][0]['id']);
        $this->assertSame('section', $content[0]['data']['tiles'][1]['target_type']);
        $this->assertSame($blockId, $content[1]['data']['block_id']);
        $this->assertSame('<p>Text</p>', $content[1]['data']['body']);
        $this->assertSame('kids', $content[2]['data']['audience']);
    }

    public function test_migration_moves_section_content_into_tiles(): void
    {
        $migration = require database_path('migrations/2026_11_11_195239_migrate_section_content_to_tiles.php');
        $sharedId = (string) Str::uuid();

        $targetId = DB::table('sections')->insertGetId(['title' => 'Ziel', 'slug' => 'ziel', 'content' => null]);
        $firstId = DB::table('sections')->insertGetId(['title' => 'Eins', 'slug' => 'eins', 'content' => json_encode([
            ['type' => 'tiles', 'data' => ['tiles' => [
                ['id' => $sharedId, 'target_type' => 'section', 'section_id' => $targetId],
                ['id' => (string) Str::uuid(), 'target_type' => 'section', 'section_id' => 9999, 'heading' => 'Weg'],
            ]]],
        ])]);
        $secondId = DB::table('sections')->insertGetId(['title' => 'Zwei', 'slug' => 'zwei', 'content' => json_encode([
            ['type' => 'text', 'data' => ['block_id' => $sharedId, 'body' => '<p>Zwei</p>']],
        ])]);

        $migration->up();

        $this->assertSame(2, DB::table('tiles')->where('section_id', $firstId)->count());
        $this->assertSame($targetId, DB::table('tiles')->where('uuid', $sharedId)->value('link_section_id'));
        $this->assertNull(DB::table('tiles')->where('heading', 'Weg')->value('link_section_id'));
        $this->assertNotSame($sharedId, DB::table('tiles')->where('section_id', $secondId)->value('uuid'));
        $this->assertSame(['body' => '<p>Zwei</p>'], json_decode(DB::table('tiles')->where('section_id', $secondId)->value('settings'), true));

        $migration->down();

        $this->assertSame(0, DB::table('tiles')->count());
        $this->assertSame('text', json_decode(DB::table('sections')->where('id', $secondId)->value('content'), true)[0]['type']);
    }
}
