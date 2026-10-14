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
        // The old participation table was never used in production; participations are enrollments now.
        Schema::dropIfExists('course_kid');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('course_kid', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('kid_id');
            $table->foreignId('course_id');
            $table->date('date')->nullable();
        });
    }
};
