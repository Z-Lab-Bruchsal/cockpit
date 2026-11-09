<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The footer becomes a grid of elements (text, address, links, social media) that can be arranged freely.
 * Null means the default arrangement, see CompanySetting::footerBlocks().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->json('footer_blocks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('footer_blocks');
        });
    }
};
