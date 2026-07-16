<?php

namespace App\Observers;

use App\Enums\TimeEntryType;
use App\Models\TimeEntry;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;

class TimeEntryObserver
{
    /**
     * @var array<int, string>
     */
    private const AUDITED_FIELDS = ['type', 'happened_at', 'note'];

    /**
     * Entry types that close a work segment, and so carry a worked_minutes value.
     *
     * @var array<int, TimeEntryType>
     */
    private const SEGMENT_CLOSING_TYPES = [TimeEntryType::BreakStart, TimeEntryType::Go];

    /**
     * Entry types that can validly open a work segment.
     *
     * @var array<int, TimeEntryType>
     */
    private const SEGMENT_OPENING_TYPES = [TimeEntryType::Come, TimeEntryType::BreakEnd];

    public function created(TimeEntry $timeEntry): void
    {
        $timeEntry->audits()->create([
            'action' => 'created',
            'field' => null,
            'old_value' => null,
            'new_value' => json_encode($timeEntry->only(self::AUDITED_FIELDS)),
            'changed_by_user_id' => $this->actingUserId(),
            'changed_at' => now(),
        ]);
    }

    public function updated(TimeEntry $timeEntry): void
    {
        $changes = array_intersect_key($timeEntry->getChanges(), array_flip(self::AUDITED_FIELDS));

        foreach ($changes as $field => $newValue) {
            $timeEntry->audits()->create([
                'action' => 'updated',
                'field' => $field,
                'old_value' => $this->stringify($timeEntry->getOriginal($field)),
                'new_value' => $this->stringify($newValue),
                'changed_by_user_id' => $this->actingUserId(),
                'changed_at' => now(),
            ]);
        }
    }

    /**
     * Keep worked_minutes in sync on every create/update, including corrections
     * made after the fact (backfilled or edited happened_at/type values).
     */
    public function saved(TimeEntry $timeEntry): void
    {
        $this->recomputeWorkedMinutes($timeEntry);
        $this->recomputeNextEntry($timeEntry);
    }

    public function deleted(TimeEntry $timeEntry): void
    {
        $timeEntry->audits()->create([
            'action' => 'deleted',
            'field' => null,
            'old_value' => json_encode($timeEntry->only(self::AUDITED_FIELDS)),
            'new_value' => null,
            'changed_by_user_id' => $this->actingUserId(),
            'changed_at' => now(),
        ]);

        // The entry that used to follow the deleted one now has a different
        // predecessor, so its worked_minutes may need to change.
        $this->recomputeNextEntry($timeEntry);
    }

    private function recomputeWorkedMinutes(TimeEntry $timeEntry): void
    {
        $workedMinutes = $this->calculateWorkedMinutes($timeEntry);

        if ($timeEntry->worked_minutes === $workedMinutes) {
            return;
        }

        $timeEntry->worked_minutes = $workedMinutes;
        $timeEntry->saveQuietly();
    }

    private function calculateWorkedMinutes(TimeEntry $timeEntry): ?int
    {
        if (! in_array($timeEntry->type, self::SEGMENT_CLOSING_TYPES, true)) {
            return null;
        }

        $previous = $this->adjacentEntry($timeEntry, before: true);

        if (! $previous || ! in_array($previous->type, self::SEGMENT_OPENING_TYPES, true)) {
            return null;
        }

        return (int) round($previous->happened_at->diffInMinutes($timeEntry->happened_at));
    }

    private function recomputeNextEntry(TimeEntry $timeEntry): void
    {
        $next = $this->adjacentEntry($timeEntry, before: false);

        if ($next) {
            $this->recomputeWorkedMinutes($next);
        }
    }

    private function adjacentEntry(TimeEntry $timeEntry, bool $before): ?TimeEntry
    {
        $operator = $before ? '<' : '>';
        $tieBreakOperator = $before ? '<' : '>';
        $direction = $before ? 'desc' : 'asc';

        return TimeEntry::query()
            ->where('user_id', $timeEntry->user_id)
            ->where('id', '!=', $timeEntry->id)
            ->where(fn (Builder $query) => $query
                ->where('happened_at', $operator, $timeEntry->happened_at)
                ->orWhere(fn (Builder $query) => $query
                    ->where('happened_at', $timeEntry->happened_at)
                    ->where('id', $tieBreakOperator, $timeEntry->id)))
            ->orderBy('happened_at', $direction)
            ->orderBy('id', $direction)
            ->first();
    }

    private function actingUserId(): ?int
    {
        return filament()->auth()->user()?->id;
    }

    private function stringify(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof BackedEnum => (string) $value->value,
            default => (string) $value,
        };
    }
}
