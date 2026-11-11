<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiles are the content of a section: one ordered list of tiles in the 6-column homepage grid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiles', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->unsignedTinyInteger('width')->default(2);
            $table->unsignedTinyInteger('height')->default(1);
            $table->string('style')->nullable();
            $table->string('eyebrow')->nullable();
            $table->string('heading')->nullable();
            $table->text('text')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('link_type')->nullable();
            $table->foreignId('link_section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->string('link_url', 500)->nullable();
            $table->string('link_anchor', 60)->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['section_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiles');
    }
};
