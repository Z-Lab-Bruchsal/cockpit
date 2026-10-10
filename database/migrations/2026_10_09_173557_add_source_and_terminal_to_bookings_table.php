<?php

use App\Enums\BookingSource;
use App\Models\Terminal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('source')->default(BookingSource::WebLive->value);
            $table->foreignIdFor(Terminal::class)->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('recorded_by_user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(Terminal::class);
            $table->dropColumn('source');
            $table->unsignedBigInteger('recorded_by_user_id')->nullable(false)->change();
        });
    }
};
