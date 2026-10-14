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
        // Digest mails sent to a contact; used for the weekly maximum.
        Schema::create('course_digests', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('course_count');
            $table->timestamp('sent_at');
            $table->index(['contact_id', 'sent_at']);
        });

        // Queue of "new course" notifications: collected per contact and sent as one digest mail.
        Schema::create('course_instance_notifications', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('course_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('matching_kid_ids');
            $table->foreignId('course_digest_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->unique(['course_instance_id', 'contact_id']);
            $table->index(['contact_id', 'sent_at']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            // Personal overrides; null = company default / no weekly maximum.
            $table->unsignedInteger('digest_hours')->nullable();
            $table->unsignedInteger('max_course_mails_per_week')->nullable();
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->unsignedInteger('digest_hours')->default(4);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('digest_hours');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['digest_hours', 'max_course_mails_per_week']);
        });

        Schema::dropIfExists('course_instance_notifications');
        Schema::dropIfExists('course_digests');
    }
};
