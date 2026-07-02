<?php

namespace App\Console\Commands;

use App\Mail\TodoMail;
use App\Models\Todo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('app:send-todo-follow-up-reminders')]
#[Description('Send reminder emails for todos whose follow-up date has been reached')]
class SendTodoFollowUpReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $todos = Todo::query()
            ->whereNull('done_date')
            ->whereNull('follow_up_notified_at')
            ->whereNotNull('follow_up')
            ->whereDate('follow_up', '<=', today())
            ->get();

        foreach ($todos as $todo) {
            foreach ($todo->getassignetusers() as $user) {
                Mail::to($user)->send(new TodoMail($todo, 'Todo Wiedervorlage', 'Die Wiedervorlage für folgende Todo ist erreicht:'));
            }

            $todo->follow_up_notified_at = now();
            $todo->saveQuietly();
        }
    }
}
