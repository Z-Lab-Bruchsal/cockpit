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
        Schema::create('work_time_settings', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->decimal('threshold_1_hours', 4, 2)->default(6);
            $table->unsignedInteger('break_1_minutes')->default(30);
            $table->decimal('threshold_2_hours', 4, 2)->default(9);
            $table->unsignedInteger('break_2_minutes')->default(45);
            $table->unsignedInteger('minimum_qualifying_break_minutes')->default(15);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_time_settings');
    }
};
