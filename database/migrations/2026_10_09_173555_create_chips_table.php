<?php

use App\Models\Terminal;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chips', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('uid')->unique();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('first_seen_at')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->foreignIdFor(Terminal::class, 'last_seen_terminal_id')->nullable()->constrained('terminals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chips');
    }
};
