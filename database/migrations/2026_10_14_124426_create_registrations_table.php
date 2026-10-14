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
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('cart_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_instance_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('source')->default('homepage');
            // Contact details as entered; the contact record is only created or linked after the double opt-in.
            $table->string('email')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('wants_interest_mails')->default(false);
            $table->text('message')->nullable();
            $table->json('school_class_data')->nullable();
            $table->string('confirmation_token_hash', 64)->nullable()->index();
            $table->string('manage_token_hash', 64)->nullable()->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            // Siblings exceeding the free seats, or a waitlist family too big for the freed seats, need a decision by the course lead.
            $table->boolean('needs_approval')->default(false);
            $table->timestamp('approval_requested_at')->nullable();
            $table->timestamp('approval_decided_at')->nullable();
            // Free seats at the time the course lead declined moving this family up; it is asked again once more seats are free.
            $table->unsignedInteger('waitlist_skipped_at_free_seats')->nullable();
            $table->index(['course_instance_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
