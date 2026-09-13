<?php

namespace App\Services\Reports;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BookingReportService
{
    public static function formatMinutes(?int $minutes): string
    {
        $minutes ??= 0;

        return sprintf('%dh %02dmin', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * @return Collection<int, Booking>
     */
    public function monthlyBookings(User $user, int $year, int $month): Collection
    {
        [$start, $end] = $this->monthRangeUtc($year, $month);

        return Booking::query()
            ->where('user_id', $user->id)
            ->whereBetween('start_at', [$start, $end])
            ->orderBy('start_at')
            ->get();
    }

    public function monthlyTotalMinutes(User $user, int $year, int $month): int
    {
        return (int) $this->monthlyBookings($user, $year, $month)->sum('worked_minutes');
    }

    /**
     * @return array<int, int> month (1-12) => minutes
     */
    public function yearlyMonthlyTotals(User $user, int $year): array
    {
        [$start, $end] = $this->yearRangeUtc($year);
        $timezone = config('app.business_timezone');

        $totals = array_fill(1, 12, 0);

        Booking::query()
            ->where('user_id', $user->id)
            ->whereBetween('start_at', [$start, $end])
            ->get(['start_at', 'worked_minutes'])
            ->each(function (Booking $booking) use (&$totals, $timezone) {
                $month = (int) $booking->start_at->copy()->setTimezone($timezone)->format('n');
                $totals[$month] += (int) ($booking->worked_minutes ?? 0);
            });

        return $totals;
    }

    public function yearlyTotalMinutes(User $user, int $year): int
    {
        return array_sum($this->yearlyMonthlyTotals($user, $year));
    }

    /**
     * @return Collection<int, array{user: User, minutes: int}>
     */
    public function monthlyTotalsForAllUsers(int $year, int $month): Collection
    {
        [$start, $end] = $this->monthRangeUtc($year, $month);

        return $this->totalsForAllUsers($start, $end);
    }

    /**
     * @return Collection<int, array{user: User, minutes: int}>
     */
    public function yearlyTotalsForAllUsers(int $year): Collection
    {
        [$start, $end] = $this->yearRangeUtc($year);

        return $this->totalsForAllUsers($start, $end);
    }

    /**
     * @return Collection<int, array{user: User, minutes: int}>
     */
    private function totalsForAllUsers(Carbon $start, Carbon $end): Collection
    {
        $totals = Booking::query()
            ->whereBetween('start_at', [$start, $end])
            ->selectRaw('user_id, SUM(worked_minutes) as total_minutes')
            ->groupBy('user_id')
            ->pluck('total_minutes', 'user_id');

        return User::query()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'user' => $user,
                'minutes' => (int) ($totals[$user->id] ?? 0),
            ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function monthRangeUtc(int $year, int $month): array
    {
        $timezone = config('app.business_timezone');
        $start = Carbon::create($year, $month, 1, 0, 0, 0, $timezone)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [$start->clone()->setTimezone('UTC'), $end->clone()->setTimezone('UTC')];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function yearRangeUtc(int $year): array
    {
        $timezone = config('app.business_timezone');
        $start = Carbon::create($year, 1, 1, 0, 0, 0, $timezone)->startOfYear();
        $end = $start->copy()->endOfYear();

        return [$start->clone()->setTimezone('UTC'), $end->clone()->setTimezone('UTC')];
    }
}
