<?php

namespace App\Services\WorkTime;

use App\Enums\TimeClockState;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

class TimeClockService
{
    public function currentState(User $user): TimeClockState
    {
        return $this->openBooking($user) ? TimeClockState::Working : TimeClockState::NotClockedIn;
    }

    public function clockIn(User $user, User $actingAs, ?Carbon $at = null): Booking
    {
        if ($this->openBooking($user)) {
            throw new RuntimeException('Cannot clock in from state working.');
        }

        return Booking::create([
            'user_id' => $user->id,
            'start_at' => $at ?? now(),
            'recorded_by_user_id' => $actingAs->id,
        ]);
    }

    public function clockOut(User $user, User $actingAs, ?Carbon $at = null): Booking
    {
        $booking = $this->openBooking($user);

        if (! $booking) {
            throw new RuntimeException('Cannot clock out from state not_clocked_in.');
        }

        $booking->update([
            'end_at' => $at ?? now(),
            'recorded_by_user_id' => $actingAs->id,
        ]);

        return $booking;
    }

    private function openBooking(User $user): ?Booking
    {
        return Booking::query()
            ->where('user_id', $user->id)
            ->whereNull('end_at')
            ->latest('start_at')
            ->latest('id')
            ->first();
    }
}
