<?php

use App\Models\TimeEntry;
use App\Models\User;
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
        Schema::create('time_entry_audits', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignIdFor(TimeEntry::class);
            $table->string('action');
            $table->string('field')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignIdFor(User::class, 'changed_by_user_id')->nullable();
            $table->dateTime('changed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_entry_audits');
    }
};
