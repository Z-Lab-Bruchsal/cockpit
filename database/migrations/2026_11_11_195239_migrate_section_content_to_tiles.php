<?php

use App\Services\Homepage\LegacyContentConverter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The content blocks of every section become tiles. sections.content is kept as a backup for now
 * and dropped in a later migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sectionIds = DB::table('sections')->pluck('id')->flip();
        $seenUuids = [];
        $now = now();

        DB::table('sections')->orderBy('id')->get(['id', 'content'])->each(function (object $section) use ($sectionIds, &$seenUuids, $now): void {
            $rows = LegacyContentConverter::toTiles(json_decode((string) $section->content, true) ?: []);

            foreach ($rows as $row) {
                if (isset($seenUuids[$row['uuid']])) {
                    $row['uuid'] = (string) Str::uuid();
                }

                $seenUuids[$row['uuid']] = true;

                if ($row['link_section_id'] !== null && ! $sectionIds->has($row['link_section_id'])) {
                    $row['link_section_id'] = null;
                }

                DB::table('tiles')->insert([
                    ...$row,
                    'section_id' => $section->id,
                    'settings' => $row['settings'] === null ? null : json_encode($row['settings']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('tiles')->orderBy('sort')->orderBy('id')->get()
            ->groupBy('section_id')
            ->each(function ($tiles, $sectionId): void {
                DB::table('sections')->where('id', $sectionId)->update([
                    'content' => json_encode(LegacyContentConverter::toContent($tiles->map(fn (object $tile): array => (array) $tile))),
                ]);
            });

        DB::table('tiles')->delete();
    }
};
