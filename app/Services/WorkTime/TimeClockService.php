<?php

namespace App\Services\WorkTime;

use App\Enums\TimeClockState;
use App\Enums\TimeEntryType;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TimeClockService
{
    public function currentState(User $user): TimeClockState
    {
        $lastType = TimeEntry::query()
            ->where('user_id', $user->id)
            ->latest('happened_at')
            ->latest('id')
            ->value('type');

        return match ($lastType) {
            null, TimeEntryType::Go => TimeClockState::NotClockedIn,
            TimeEntryType::Come, TimeEntryType::BreakEnd => TimeClockState::Working,
            TimeEntryType::BreakStart => TimeClockState::OnBreak,
        };
    }

    public function clockIn(User $user, User $actingAs, ?Carbon $at = null): TimeEntry
    {
        return $this->transition($user, $actingAs, TimeClockState::NotClockedIn, TimeEntryType::Come, $at);
    }

    public function startBreak(User $user, User $actingAs, ?Carbon $at = null): TimeEntry
    {
        return $this->transition($user, $actingAs, TimeClockState::Working, TimeEntryType::BreakStart, $at);
    }

    public function endBreak(User $user, User $actingAs, ?Carbon $at = null): TimeEntry
    {
        return $this->transition($user, $actingAs, TimeClockState::OnBreak, TimeEntryType::BreakEnd, $at);
    }

    /**
     * Clock out. If the user is currently on a break, an implicit break-end
     * entry is inserted at the same timestamp first, so break duration is
     * exact and no work time accrues between the break and clocking out.
     */
    public function clockOut(User $user, User $actingAs, ?Carbon $at = null): TimeEntry
    {
        $at ??= now();

        return DB::transaction(function () use ($user, $actingAs, $at) {
            $state = $this->currentState($user);

            if ($state === TimeClockState::OnBreak) {
                $this->recordEntry($user, $actingAs, TimeEntryType::BreakEnd, $at);
            } elseif ($state !== TimeClockState::Working) {
                throw new RuntimeException("Cannot clock out from state {$state->value}.");
            }

            return $this->recordEntry($user, $actingAs, TimeEntryType::Go, $at);
        });
    }

    private function transition(User $user, User $actingAs, TimeClockState $requiredState, TimeEntryType $type, ?Carbon $at = null): TimeEntry
    {
        $at ??= now();

        return DB::transaction(function () use ($user, $actingAs, $requiredState, $type, $at) {
            $state = $this->currentState($user);

            if ($state !== $requiredState) {
                throw new RuntimeException("Cannot record {$type->value} from state {$state->value}.");
            }

            return $this->recordEntry($user, $actingAs, $type, $at);
        });
    }

    private function recordEntry(User $user, User $actingAs, TimeEntryType $type, Carbon $at): TimeEntry
    {
        return TimeEntry::create([
            'user_id' => $user->id,
            'type' => $type,
            'happened_at' => $at,
            'recorded_by_user_id' => $actingAs->id,
        ]);
    }
}
