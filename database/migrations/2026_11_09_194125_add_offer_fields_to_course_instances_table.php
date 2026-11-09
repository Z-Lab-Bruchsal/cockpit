<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course instances become offers of their own on the homepage: drop-in offers without registration,
 * prominence in offer lists, and card and detail page content that overrides the course.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_instances', function (Blueprint $table) {
            $table->string('participation')->default('registration')->after('status');
            $table->string('prominence')->default('standard')->after('participation');
            $table->string('schedule_text')->nullable()->after('prominence');
            $table->string('schedule_note')->nullable()->after('schedule_text');
            $table->date('available_from')->nullable()->after('schedule_note');
            $table->date('available_until')->nullable()->after('available_from');
            $table->string('card_label')->nullable();
            $table->string('card_label_sub')->nullable();
            $table->string('teaser', 300)->nullable();
            $table->text('description')->nullable();
            $table->string('card_image_path')->nullable();
            $table->string('card_image_alt')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_alt')->nullable();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->string('default_participation')->default('registration')->after('audience');
        });
    }

    public function down(): void
    {
        Schema::table('course_instances', function (Blueprint $table) {
            $table->dropColumn([
                'participation', 'prominence', 'schedule_text', 'schedule_note', 'available_from', 'available_until',
                'card_label', 'card_label_sub', 'teaser', 'description', 'card_image_path', 'card_image_alt', 'image_path', 'image_alt',
            ]);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('default_participation');
        });
    }
};
