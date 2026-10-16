<?php

use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Todos can be assigned to several users and groups at once and,
     * if the creator wants that, be completed by every assignee on their own.
     */
    public function up(): void
    {
        Schema::create('todo_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Todo::class)->constrained()->cascadeOnDelete();
            $table->morphs('assignee');
            $table->unique(['todo_id', 'assignee_type', 'assignee_id']);
        });

        Schema::create('todo_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Todo::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['todo_id', 'user_id']);
        });

        Schema::table('todos', function (Blueprint $table) {
            $table->boolean('individual_completion')->default(false)->after('review');
        });

        DB::table('todos')
            ->whereNotNull('todoable_type')
            ->whereNotNull('todoable_id')
            ->orderBy('id')
            ->each(function (object $todo): void {
                DB::table('todo_assignees')->insert([
                    'todo_id' => $todo->id,
                    'assignee_type' => $todo->todoable_type,
                    'assignee_id' => $todo->todoable_id,
                ]);
            });

        Schema::table('todos', function (Blueprint $table) {
            $table->dropColumn(['todoable_type', 'todoable_id']);
        });
    }

    /**
     * Restores the single assignment; only the first assignee of each todo survives.
     */
    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->string('todoable_type')->nullable();
            $table->integer('todoable_id')->nullable();
        });

        DB::table('todo_assignees')
            ->orderByDesc('id')
            ->each(function (object $assignee): void {
                DB::table('todos')->where('id', $assignee->todo_id)->update([
                    'todoable_type' => $assignee->assignee_type,
                    'todoable_id' => $assignee->assignee_id,
                ]);
            });

        Schema::table('todos', function (Blueprint $table) {
            $table->dropColumn('individual_completion');
        });

        Schema::dropIfExists('todo_completions');
        Schema::dropIfExists('todo_assignees');
    }
};
