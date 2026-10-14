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
        Schema::create('course_templates', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->string('card_style')->default('image_top');
            $table->string('detail_layout')->default('hero_image');
            $table->string('accent_color')->nullable();
            $table->boolean('show_age_range')->default(true);
            $table->boolean('show_interests')->default(true);
            $table->boolean('show_free_seats')->default(true);
            $table->string('default_card_image_path')->nullable();
            $table->string('default_image_path')->nullable();
            $table->json('extra_content')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_templates');
    }
};
