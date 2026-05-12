<?php

use App\Models\Belt;
use App\Models\Course;
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
        Schema::create('belt_course', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignIdFor(Belt::class);
            $table->foreignIdFor(Course::class);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('belt_course');
    }
};
