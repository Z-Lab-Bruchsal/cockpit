<?php

namespace App\Services\WorkTime;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Carbon;

class WorkTimeCalculator
{
    public function workedMinutes(User $user, Carbon $date): int
    {
        $timezone = config('app.business_timezone');

        $startOfDay = Carbon::parse($date->toDateString(), $timezone)->startOfDay();
        $endOfDay = $startOfDay->copy()->endOfDay();

        return (int) Booking::query()
            ->where('user_id', $user->id)
            ->whereNotNull('end_at')
            ->whereBetween('start_at', [$startOfDay->clone()->setTimezone('UTC'), $endOfDay->clone()->setTimezone('UTC')])
            ->sum('worked_minutes');
    }

    /**
     * Minutes elapsed since the user clocked in, or null if they aren't
     * currently clocked in.
     */
    public function currentSegmentMinutes(User $user): ?int
    {
        $openBooking = Booking::query()
            ->where('user_id', $user->id)
            ->whereNull('end_at')
            ->latest('start_at')
            ->latest('id')
            ->first();

        if (! $openBooking) {
            return null;
        }

        return (int) round($openBooking->start_at->diffInMinutes(now()));
    }

    public function weeklyWorkedMinutes(User $user, Carbon $weekStart): int
    {
        $minutes = 0;

        for ($i = 0; $i < 7; $i++) {
            $minutes += $this->workedMinutes($user, $weekStart->copy()->addDays($i));
        }

        return $minutes;
    }
}
