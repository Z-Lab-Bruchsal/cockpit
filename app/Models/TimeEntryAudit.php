<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['time_entry_id', 'action', 'field', 'old_value', 'new_value', 'changed_by_user_id', 'changed_at'])]
class TimeEntryAudit extends Model
{
    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function timeEntry(): BelongsTo
    {
        return $this->belongsTo(TimeEntry::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
