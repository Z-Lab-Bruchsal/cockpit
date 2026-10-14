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
        Schema::create('contact_email_changes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('old_email');
            $table->string('new_email');
            $table->string('old_token_hash', 64)->unique();
            $table->string('new_token_hash', 64)->nullable()->unique();
            $table->timestamp('old_confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_email_changes');
    }
};
