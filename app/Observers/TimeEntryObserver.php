<?php

namespace App\Observers;

use App\Models\TimeEntry;
use BackedEnum;

class TimeEntryObserver
{
    /**
     * @var array<int, string>
     */
    private const AUDITED_FIELDS = ['type', 'happened_at', 'note'];

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
