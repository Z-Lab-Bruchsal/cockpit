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
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('waitlist_offer_hours')->nullable();
        });

        Schema::table('course_instances', function (Blueprint $table) {
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('waitlist_offer_hours')->nullable();
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->unsignedInteger('waitlist_offer_hours')->default(48);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_user_id');
            $table->dropColumn('waitlist_offer_hours');
        });

        Schema::table('course_instances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_user_id');
            $table->dropColumn('waitlist_offer_hours');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('waitlist_offer_hours');
        });
    }
};
