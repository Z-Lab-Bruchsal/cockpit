<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->unsignedTinyInteger('tile_width')->default(2)->after('layout');
            $table->unsignedTinyInteger('tile_height')->default(1)->after('tile_width');
        });

        DB::table('sections')
            ->where('layout', 'full_width')
            ->update(['tile_width' => 6, 'layout' => 'standard']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('sections')
            ->where('tile_width', 6)
            ->update(['layout' => 'full_width']);

        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn(['tile_width', 'tile_height']);
        });
    }
};
