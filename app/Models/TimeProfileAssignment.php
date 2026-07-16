<?php

namespace App\Models;

use Database\Factories\TimeProfileAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class TimeProfileAssignment extends Model
{
    /** @use HasFactory<TimeProfileAssignmentFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'time_profile_id', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function timeProfile(): BelongsTo
    {
        return $this->belongsTo(TimeProfile::class);
    }

    public static function overlapsExisting(int $userId, Carbon $from, ?Carbon $to, ?int $ignoreId = null): bool
    {
        return static::query()
            ->where('user_id', $userId)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->whereDate('effective_from', '<=', $to?->toDateString() ?? '9999-12-31')
            ->where(fn (Builder $query) => $query
                ->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $from->toDateString()))
            ->exists();
    }

    public static function currentFor(User $user, ?Carbon $onDate = null): ?TimeProfile
    {
        $onDate ??= now();

        return static::query()
            ->where('user_id', $user->id)
            ->whereDate('effective_from', '<=', $onDate->toDateString())
            ->where(fn (Builder $query) => $query
                ->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $onDate->toDateString()))
            ->orderByDesc('effective_from')
            ->first()
            ?->timeProfile;
    }
}
