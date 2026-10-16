<?php

namespace App\Observers;

use App\Mail\TodoMail;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class TodoObserver
{
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
