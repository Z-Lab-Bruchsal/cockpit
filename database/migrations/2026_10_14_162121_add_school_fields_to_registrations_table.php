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
        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('contact_id')->constrained('contacts')->nullOnDelete();
            $table->string('venue')->nullable();
            $table->foreignId('replaces_registration_id')->nullable()->constrained('registrations')->nullOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestamp('teacher_reminded_at')->nullable();
            $table->timestamp('teacher_confirmed_at')->nullable();
            $table->timestamp('staff_remind_at')->nullable();
            $table->timestamp('staff_reminded_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_id');
            $table->dropConstrainedForeignId('replaces_registration_id');
            $table->dropColumn(['venue', 'decision_note', 'teacher_reminded_at', 'teacher_confirmed_at', 'staff_remind_at', 'staff_reminded_at']);
        });
    }
};
