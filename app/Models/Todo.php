<?php

namespace App\Models;

use App\Observers\TodoObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable('name', 'content', 'user_id', 'todoable_type', 'todoable_id', 'due_date', 'done_date', 'follow_up', 'review')]
#[ObservedBy([TodoObserver::class])]
class Todo extends Model
{
    public function todoable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function is_owner_or_assigned() {
        if(filament()->auth()->user()->id == $this->user_id) return true;
        if($this->todoable_type == Group::class && in_array(filament()->auth()->user()->id, $this->todoable()->first()->get()->users()->get()->pluck('id')->toArray())) return true;
        if($this->todoable_type == User::class && $this->todoable()->first()->get()->id == filament()->auth()->user()->id) return true;
        else return false;
    }

    public function getassignetusers() {
        if($this->todoable_type == Group::class) return $this->todoable()->first()->users()->get();
        if($this->todoable_type == User::class) return [$this->todoable()->first()->get()];
    }
}
