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
        Schema::create('interest_kid', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kid_id')->constrained()->cascadeOnDelete();
            $table->unique(['interest_id', 'kid_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interest_kid');
    }
};
