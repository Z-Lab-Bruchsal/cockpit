<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->string('tile_image_alt')->nullable()->after('tile_image_path');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->string('card_image_alt')->nullable()->after('card_image_path');
            $table->string('image_alt')->nullable()->after('image_path');
        });

        Schema::table('course_templates', function (Blueprint $table) {
            $table->string('default_card_image_alt')->nullable()->after('default_card_image_path');
            $table->string('default_image_alt')->nullable()->after('default_image_path');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('logo_alt')->nullable()->after('logo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn('tile_image_alt');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['card_image_alt', 'image_alt']);
        });

        Schema::table('course_templates', function (Blueprint $table) {
            $table->dropColumn(['default_card_image_alt', 'default_image_alt']);
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('logo_alt');
        });
    }
};
