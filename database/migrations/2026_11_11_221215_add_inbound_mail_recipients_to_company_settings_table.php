<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Users and groups (recipient keys) who receive mails sent to the cockpit mailbox as internal messages.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->json('inbound_mail_recipients')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('inbound_mail_recipients');
        });
    }
};
