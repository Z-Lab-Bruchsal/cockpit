<?php

namespace App\Observers;

use App\Models\Booking;
use BackedEnum;

class BookingObserver
{
    /**
     * @var array<int, string>
     */
    private const AUDITED_FIELDS = ['start_at', 'end_at', 'note'];

    public function created(Booking $booking): void
    {
        $booking->audits()->create([
            'action' => 'created',
            'field' => null,
            'old_value' => null,
            'new_value' => json_encode($booking->only(self::AUDITED_FIELDS)),
            'changed_by_user_id' => $this->actingUserId(),
            'changed_at' => now(),
        ]);
    }

    public function updated(Booking $booking): void
    {
        $changes = array_intersect_key($booking->getChanges(), array_flip(self::AUDITED_FIELDS));

        foreach ($changes as $field => $newValue) {
            $booking->audits()->create([
                'action' => 'updated',
                'field' => $field,
                'old_value' => $this->stringify($booking->getOriginal($field)),
                'new_value' => $this->stringify($newValue),
                'changed_by_user_id' => $this->actingUserId(),
                'changed_at' => now(),
            ]);
        }
    }

    /**
     * Keep worked_minutes in sync on every create/update, including
     * corrections made after the fact (backfilled or edited start/end times).
     */
    public function saved(Booking $booking): void
    {
        $workedMinutes = $booking->start_at && $booking->end_at
            ? (int) round($booking->start_at->diffInMinutes($booking->end_at))
            : null;

        if ($booking->worked_minutes === $workedMinutes) {
            return;
        }

        $booking->worked_minutes = $workedMinutes;
        $booking->saveQuietly();
    }

    public function deleted(Booking $booking): void
    {
        $booking->audits()->create([
            'action' => 'deleted',
            'field' => null,
            'old_value' => json_encode($booking->only(self::AUDITED_FIELDS)),
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
