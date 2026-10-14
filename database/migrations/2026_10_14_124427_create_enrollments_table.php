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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kid_id')->nullable()->constrained()->nullOnDelete();
            // Child as entered at checkout, used until the kid record exists after the double opt-in.
            $table->string('kid_name')->nullable();
            $table->date('kid_birthdate')->nullable();
            $table->string('status');
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('waitlist_position')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->index(['course_instance_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
