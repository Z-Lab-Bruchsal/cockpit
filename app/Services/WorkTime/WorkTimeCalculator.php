<?php

namespace App\Services\WorkTime;

use App\Enums\TimeEntryType;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkTimeSetting;
use Illuminate\Support\Carbon;

class WorkTimeCalculator
{
    /**
     * Pair a day's punch events into work/break segments.
     *
     * $date is interpreted as a business-local (config('app.business_timezone'))
     * calendar day, since break-compliance thresholds are wall-clock concepts.
     * Storage stays UTC; entries are converted to business-local time for grouping.
     *
     * @return array{work: array<int, array{start: Carbon, end: Carbon}>, break: array<int, array{start: Carbon, end: Carbon}>}
     */
    public function sessionsForDay(User $user, Carbon $date): array
    {
        $timezone = config('app.business_timezone');

        $startOfDay = Carbon::parse($date->toDateString(), $timezone)->startOfDay();
        $endOfDay = $startOfDay->copy()->endOfDay();

        $entries = TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereBetween('happened_at', [$startOfDay->clone()->setTimezone('UTC'), $endOfDay->clone()->setTimezone('UTC')])
            ->orderBy('happened_at')
            ->orderBy('id')
            ->get();

        $work = [];
        $breaks = [];
        $workStart = null;
        $breakStart = null;

        foreach ($entries as $entry) {
            $happenedAt = $entry->happened_at->copy()->setTimezone($timezone);

            if ($entry->type === TimeEntryType::Come) {
                $workStart = $happenedAt;
            } elseif ($entry->type === TimeEntryType::BreakStart) {
                if ($workStart !== null) {
                    $work[] = ['start' => $workStart, 'end' => $happenedAt];
                    $workStart = null;
                }
                $breakStart = $happenedAt;
            } elseif ($entry->type === TimeEntryType::BreakEnd) {
                if ($breakStart !== null) {
                    $breaks[] = ['start' => $breakStart, 'end' => $happenedAt];
                    $breakStart = null;
                }
                $workStart = $happenedAt;
            } elseif ($entry->type === TimeEntryType::Go) {
                if ($workStart !== null) {
                    $work[] = ['start' => $workStart, 'end' => $happenedAt];
                    $workStart = null;
                }
            }
        }

        return ['work' => $work, 'break' => $breaks];
    }

    public function workedMinutes(User $user, Carbon $date): int
    {
        return $this->sumMinutes($this->sessionsForDay($user, $date)['work']);
    }

    /**
     * Minutes elapsed in the work segment that is currently open (i.e. since
     * the user's last "come" or "break_end"), or null if they aren't
     * currently in an open work segment (not clocked in, on a break, or
     * already clocked out).
     */
    public function currentSegmentMinutes(User $user): ?int
    {
        $lastEntry = TimeEntry::query()
            ->where('user_id', $user->id)
            ->latest('happened_at')
            ->latest('id')
            ->first();

        if (! $lastEntry || ! in_array($lastEntry->type, [TimeEntryType::Come, TimeEntryType::BreakEnd], true)) {
            return null;
        }

        return (int) round($lastEntry->happened_at->diffInMinutes(now()));
    }

    public function qualifyingBreakMinutes(User $user, Carbon $date): int
    {
        $minimum = WorkTimeSetting::current()->minimum_qualifying_break_minutes;

        $qualifying = array_filter(
            $this->sessionsForDay($user, $date)['break'],
            fn (array $segment) => $segment['start']->diffInMinutes($segment['end']) >= $minimum,
        );

        return $this->sumMinutes($qualifying);
    }

    /**
     * @return array<int, string>
     */
    public function complianceWarnings(User $user, Carbon $date): array
    {
        $settings = WorkTimeSetting::current();
        $worked = $this->workedMinutes($user, $date);
        $qualifyingBreak = $this->qualifyingBreakMinutes($user, $date);

        $warnings = [];

        if ($worked > $settings->threshold_2_hours * 60 && $qualifyingBreak < $settings->break_2_minutes) {
            $warnings[] = "{$settings->break_2_minutes}-Minuten-Pause fehlt (mehr als {$settings->threshold_2_hours} Stunden gearbeitet).";
        } elseif ($worked > $settings->threshold_1_hours * 60 && $qualifyingBreak < $settings->break_1_minutes) {
            $warnings[] = "{$settings->break_1_minutes}-Minuten-Pause fehlt (mehr als {$settings->threshold_1_hours} Stunden gearbeitet).";
        }

        return $warnings;
    }

    public function weeklyWorkedMinutes(User $user, Carbon $weekStart): int
    {
        $minutes = 0;

        for ($i = 0; $i < 7; $i++) {
            $minutes += $this->workedMinutes($user, $weekStart->copy()->addDays($i));
        }

        return $minutes;
    }

    public function weeklyTargetMinutes(User $user, Carbon $weekStart): int
    {
        $minutes = 0;

        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            $profile = $user->currentTimeProfile($day);
            $minutes += $profile ? (int) round(((float) $profile->weekly_hours * 60) / 7) : 0;
        }

        return $minutes;
    }

    public function weeklyBalanceMinutes(User $user, Carbon $weekStart): int
    {
        return $this->weeklyWorkedMinutes($user, $weekStart) - $this->weeklyTargetMinutes($user, $weekStart);
    }

    /**
     * @param  array<int, array{start: Carbon, end: Carbon}>  $segments
     */
    private function sumMinutes(array $segments): int
    {
        return array_sum(array_map(
            fn (array $segment) => $segment['start']->diffInMinutes($segment['end']),
            $segments,
        ));
    }
}
