<?php

namespace App\Observers;

use App\Mail\TodoMail;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class TodoObserver
{
    /**
     * Handle the Todo "created" event.
     */
    public function created(Todo $todo): void
    {
        if ($todo->todoable_type == null) {
            return;
        }
        // if($todo->todoable_type == User::class && $todo->user_id == $todo->todoable_id) return;
        if ($todo->todoable_type == User::class) {
            Mail::to(User::find($todo->todoable()->first()))->send(new TodoMail($todo, 'Neue Todo', 'Es gibt eine neue Todo für dich:'));
        } else {
            $users = $todo->todoable()->first()->users()->get();
            foreach ($users as $user) {
                Mail::to($user)->send(new TodoMail($todo, 'Neue Todo', 'Es gibt eine neue Todo für dich:'));
            }
        }
    }

    /**
     * Handle the Todo "updating" event.
     */
    public function updating(Todo $todo): void
    {
        if ($todo->isDirty('follow_up')) {
            $todo->follow_up_notified_at = null;
        }
    }

    /**
     * Handle the Todo "updated" event.
     */
    public function updated(Todo $todo): void
    {
        $hasChanged = $todo->getChanges();
        if ($hasChanged && isset($hasChanged['done_date']) && $todo->done_date != null) {
            $user = User::find($todo->user_id);
            Mail::to($user)->send(new TodoMail($todo, 'Todo erledigt', 'Todo erledigt, du wolltest, dass du Bescheid bekommst:'));
        }
        if ($hasChanged && (isset($hasChanged['todoable_type']) || isset($hasChanged['todoable_id']))) {
            $users = $todo->getassignetusers();
            foreach ($users as $user) {
                Mail::to($user)->send(new TodoMail($todo, 'Todo zugewiesen', 'Dir wurde eine bestehende Todo zugewiesen:'));
            }

        }
    }

    /**
     * Handle the Todo "deleted" event.
     */
    public function deleted(Todo $todo): void
    {
        //
    }

    /**
     * Handle the Todo "restored" event.
     */
    public function restored(Todo $todo): void
    {
        //
    }

    /**
     * Handle the Todo "force deleted" event.
     */
    public function forceDeleted(Todo $todo): void
    {
        //
    }
}
