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
        Schema::create('course_instances', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->unsignedInteger('capacity');
            $table->boolean('waitlist_enabled')->default(false);
            $table->unsignedInteger('waitlist_limit')->nullable();
            $table->string('status')->default('draft');
            $table->dateTime('registration_opens_at')->nullable();
            $table->dateTime('registration_closes_at')->nullable();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->index(['status', 'course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_instances');
    }
};
