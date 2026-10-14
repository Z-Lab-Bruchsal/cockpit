<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('course_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->nullable()->unique();
            $table->string('teaser', 300)->nullable();
            $table->string('card_image_path')->nullable();
            $table->string('image_path')->nullable();
            $table->text('description')->nullable();
            $table->string('audience')->default('kids');
            $table->unsignedTinyInteger('min_age')->nullable();
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->unsignedInteger('default_capacity')->default(10);
            $table->boolean('default_waitlist_enabled')->default(true);
            $table->boolean('is_published')->default(false);
        });

        foreach (DB::table('courses')->whereNull('slug')->get(['id', 'name']) as $course) {
            DB::table('courses')->where('id', $course->id)->update(['slug' => Str::slug($course->name).'-'.$course->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('course_template_id');
            $table->dropUnique(['slug']);
            $table->dropColumn([
                'slug',
                'teaser',
                'card_image_path',
                'image_path',
                'description',
                'audience',
                'min_age',
                'max_age',
                'default_capacity',
                'default_waitlist_enabled',
                'is_published',
            ]);
        });
    }
};
