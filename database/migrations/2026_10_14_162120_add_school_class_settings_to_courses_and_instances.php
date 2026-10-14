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
        Schema::table('courses', function (Blueprint $table) {
            $table->string('school_format')->nullable();
            $this->reminderOverrides($table);
        });

        Schema::table('course_instances', function (Blueprint $table) {
            $this->reminderOverrides($table);
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('school_reminders_enabled')->default(true);
            $table->unsignedInteger('teacher_reminder_days')->default(14);
            $table->unsignedInteger('staff_reminder_days')->default(21);
            $table->unsignedInteger('school_reschedule_deadline_days')->default(14);
            $table->string('directions_document_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['school_format', 'school_reminders_enabled', 'teacher_reminder_days', 'staff_reminder_days', 'school_reschedule_deadline_days']);
        });

        Schema::table('course_instances', function (Blueprint $table) {
            $table->dropColumn(['school_reminders_enabled', 'teacher_reminder_days', 'staff_reminder_days', 'school_reschedule_deadline_days']);
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['school_reminders_enabled', 'teacher_reminder_days', 'staff_reminder_days', 'school_reschedule_deadline_days', 'directions_document_path']);
        });
    }

    /**
     * Empty values inherit from the course or the company settings.
     */
    protected function reminderOverrides(Blueprint $table): void
    {
        $table->boolean('school_reminders_enabled')->nullable();
        $table->unsignedInteger('teacher_reminder_days')->nullable();
        $table->unsignedInteger('staff_reminder_days')->nullable();
        $table->unsignedInteger('school_reschedule_deadline_days')->nullable();
    }
};
