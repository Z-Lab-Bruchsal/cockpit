<?php

use App\Services\Homepage\ContentIds;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tiles become content blocks: the start page becomes a section of its own, and every section that showed its
 * child sections as tiles gets a tile grid block with these children instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->boolean('is_home')->default(false)->after('is_standalone');
        });

        $sections = DB::table('sections')->orderBy('sort')->orderBy('id')->get();
        $childrenByParent = $sections->where('is_standalone', false)->groupBy(fn (object $section): string => (string) $section->parent_id);

        foreach ($sections as $section) {
            $content = json_decode((string) $section->content, true) ?: [];
            $children = $childrenByParent->get((string) $section->id, collect());

            if ($children->isNotEmpty()) {
                $content[] = $this->tilesBlock($children->all());
            }

            DB::table('sections')->where('id', $section->id)->update([
                'content' => json_encode(ContentIds::ensure($content)),
            ]);
        }

        // Fresh installations get their start page on first use, see Section::home().
        if ($sections->isEmpty() || DB::table('sections')->where('is_home', true)->exists()) {
            return;
        }

        $settings = DB::table('company_settings')->where('id', 1)->first();
        $content = [[
            'type' => 'tiles',
            'data' => ['tiles' => [[
                'style' => 'hero',
                'eyebrow' => null,
                'heading' => $settings->homepage_title ?? null ?: config('app.name'),
                'text' => filled($settings->homepage_tagline ?? null) ? '<p>'.e($settings->homepage_tagline).'</p>' : null,
                'target_type' => 'none',
                'width' => 6,
                'height' => 1,
            ]]],
        ]];

        $roots = $childrenByParent->get('', collect());

        if ($roots->isNotEmpty()) {
            $content[] = $this->tilesBlock($roots->all());
        }

        $slug = 'startseite';

        while (DB::table('sections')->where('slug', $slug)->exists()) {
            $slug .= '-1';
        }

        DB::table('sections')->insert([
            'title' => 'Startseite',
            'slug' => $slug,
            'sort' => 0,
            'is_visible' => true,
            'show_title' => false,
            'show_in_navigation' => false,
            'is_standalone' => true,
            'is_home' => true,
            'layout' => 'standard',
            'tile_width' => 2,
            'tile_height' => 1,
            'content' => json_encode(ContentIds::ensure($content)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<object>  $sections
     * @return array{type: string, data: array<string, mixed>}
     */
    protected function tilesBlock(array $sections): array
    {
        return [
            'type' => 'tiles',
            'data' => [
                'tiles' => array_map(fn (object $section): array => [
                    'style' => $section->layout === 'highlighted' ? 'highlighted' : 'standard',
                    'target_type' => 'section',
                    'section_id' => $section->id,
                    'width' => (int) ($section->tile_width ?: 2),
                    'height' => (int) ($section->tile_height ?: 1),
                ], $sections),
            ],
        ];
    }

    public function down(): void
    {
        DB::table('sections')->where('is_home', true)->delete();

        foreach (DB::table('sections')->get(['id', 'content']) as $section) {
            $content = array_values(array_filter(
                json_decode((string) $section->content, true) ?: [],
                fn (mixed $block): bool => ($block['type'] ?? null) !== 'tiles',
            ));

            DB::table('sections')->where('id', $section->id)->update(['content' => json_encode($content)]);
        }

        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn('is_home');
        });
    }
};
