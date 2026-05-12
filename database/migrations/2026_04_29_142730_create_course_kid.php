<?php

use App\Models\Course;
use App\Models\Kid;
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
        Schema::create('course_kid', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignIdFor(Kid::class);
            $table->foreignIdFor(Course::class);
            $table->date("date")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_kid');
    }
};
