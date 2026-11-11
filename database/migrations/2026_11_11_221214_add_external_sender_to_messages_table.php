<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Messages imported from the cockpit mailbox have an external sender instead of a user;
     * the mail's Message-ID prevents importing the same mail twice.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('sender_email')->nullable()->after('user_id');
            $table->string('sender_name')->nullable()->after('sender_email');
            $table->string('external_message_id')->nullable()->unique()->after('sender_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique(['external_message_id']);
            $table->dropColumn(['sender_email', 'sender_name', 'external_message_id']);
        });
    }
};
