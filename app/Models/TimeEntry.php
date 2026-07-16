<?php

namespace App\Models;

use App\Enums\TimeEntryType;
use App\Observers\TimeEntryObserver;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'type', 'happened_at', 'note', 'recorded_by_user_id'])]
#[ObservedBy([TimeEntryObserver::class])]
class TimeEntry extends Model
{
    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => TimeEntryType::class,
            'happened_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(TimeEntryAudit::class);
    }
}
