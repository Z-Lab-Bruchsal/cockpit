<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Override;

#[Fillable('name', 'content', 'user_id', 'todoable_type', 'todoable_id', 'due_date', 'done_date', 'follow_up', 'review')]
class Todo extends Model
{
    public function todoable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

}
