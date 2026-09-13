<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignIdFor(User::class);
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->unsignedInteger('worked_minutes')->nullable();
            $table->string('note')->nullable();
            $table->foreignIdFor(User::class, 'recorded_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
