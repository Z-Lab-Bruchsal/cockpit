<?php

use App\Models\Booking;
use App\Models\Chip;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_events', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignIdFor(Terminal::class)->constrained()->cascadeOnDelete();
            $table->string('chip_uid');
            $table->foreignIdFor(Chip::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('result');
            $table->foreignIdFor(Booking::class)->nullable()->constrained()->nullOnDelete();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_events');
    }
};
