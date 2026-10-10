<?php

namespace App\Services\WorkTime;

use App\Enums\BookingSource;
use App\Enums\TimeClockState;
use App\Models\Booking;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TimeClockService
{
    public function currentState(User $user): TimeClockState
    {
        return $this->openBooking($user) ? TimeClockState::Working : TimeClockState::NotClockedIn;
    }

    public function clockIn(
        User $user,
        ?User $actingAs,
        ?Carbon $at = null,
        BookingSource $source = BookingSource::WebLive,
        ?Terminal $terminal = null,
    ): Booking {
        if ($this->openBooking($user)) {
            throw new RuntimeException('Cannot clock in from state working.');
        }

        return Booking::create([
            'user_id' => $user->id,
            'start_at' => $at ?? now(),
            'recorded_by_user_id' => $actingAs?->id,
            'source' => $source,
            'terminal_id' => $terminal?->id,
        ]);
    }

    /**
     * The booking keeps the source it was started with; where it was ended is
     * visible in the terminal event log.
     */
    public function clockOut(
        User $user,
        ?User $actingAs,
        ?Carbon $at = null,
        ?Terminal $terminal = null,
    ): Booking {
        $booking = $this->openBooking($user);

        if (! $booking) {
            throw new RuntimeException('Cannot clock out from state not_clocked_in.');
        }

        $booking->update([
            'end_at' => $at ?? now(),
            'recorded_by_user_id' => $actingAs?->id,
            'terminal_id' => $booking->terminal_id ?? $terminal?->id,
        ]);

        return $booking;
    }

    /**
     * Clock out if the user has an open booking, otherwise clock in. The returned
     * booking has an end_at exactly when the toggle was a clock-out.
     */
    public function toggle(
        User $user,
        ?User $actingAs,
        ?Carbon $at = null,
        BookingSource $source = BookingSource::WebLive,
        ?Terminal $terminal = null,
    ): Booking {
        return DB::transaction(function () use ($user, $actingAs, $at, $source, $terminal): Booking {
            $isWorking = Booking::query()
                ->where('user_id', $user->id)
                ->whereNull('end_at')
                ->lockForUpdate()
                ->exists();

            return $isWorking
                ? $this->clockOut($user, $actingAs, $at, $terminal)
                : $this->clockIn($user, $actingAs, $at, $source, $terminal);
        });
    }

    /**
     * The most recent clock-in or clock-out time of the user, used to ignore
     * accidental double taps on a terminal.
     */
    public function lastTransitionAt(User $user): ?Carbon
    {
        $latest = Booking::query()
            ->where('user_id', $user->id)
            ->latest('start_at')
            ->latest('id')
            ->first();

        if (! $latest) {
            return null;
        }

        return $latest->end_at ?? $latest->start_at;
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
