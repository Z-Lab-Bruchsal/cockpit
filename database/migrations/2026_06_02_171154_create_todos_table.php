<?php

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
        Schema::create('todos', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name');
            $table->text('content')->nullable();
            $table->foreignIdFor(User::class);
            $table->string('todoable_type')->nullable();
            $table->integer('todoable_id')->nullable();
            $table->date('due_date')->nullable();
            $table->date('done_date')->nullable();
            $table->date('follow_up')->nullable();
            $table->boolean('review')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('todos');
    }
};
