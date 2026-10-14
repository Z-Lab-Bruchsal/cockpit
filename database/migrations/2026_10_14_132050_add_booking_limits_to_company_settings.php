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
        Schema::table('company_settings', function (Blueprint $table) {
            // At most this many homepage bookings per child within the last hour / the current day; 0 disables a limit.
            $table->unsignedInteger('booking_limit_per_hour')->default(2);
            $table->unsignedInteger('booking_limit_per_day')->default(4);
            $table->unsignedInteger('max_seats_per_registration')->default(5);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['booking_limit_per_hour', 'booking_limit_per_day', 'max_seats_per_registration']);
        });
    }
};
