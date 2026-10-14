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
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('parent_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort')->default(0);
            $table->string('teaser', 300)->nullable();
            $table->string('tile_image_path')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->boolean('show_title')->default(true);
            $table->boolean('show_in_navigation')->default(true);
            $table->boolean('is_standalone')->default(false);
            $table->string('layout')->default('standard');
            $table->json('content')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
