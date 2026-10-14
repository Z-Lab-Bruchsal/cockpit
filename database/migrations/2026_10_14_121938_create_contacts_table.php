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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('type')->default('parent');
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('street')->nullable();
            $table->string('zip')->nullable();
            $table->string('city')->nullable();
            $table->string('contact_person')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('interest_mails_consent_at')->nullable();
            $table->string('unsubscribe_token', 64)->unique();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
