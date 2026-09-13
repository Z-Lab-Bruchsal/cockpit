<?php

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_audits', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignIdFor(Booking::class);
            $table->string('action');
            $table->string('field')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignIdFor(User::class, 'changed_by_user_id')->nullable();
            $table->dateTime('changed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_audits');
    }
};
