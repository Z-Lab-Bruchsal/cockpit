<?php

namespace Tests\Feature\TimeClock;

use App\Enums\TimeClockState;
use App\Enums\TimeEntryType;
use App\Models\User;
use App\Services\WorkTime\TimeClockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ClockStateMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_day_cycle_transitions_through_all_states(): void
    {
        $user = User::factory()->create();
        $service = new TimeClockService;

        $this->assertSame(TimeClockState::NotClockedIn, $service->currentState($user));

        $service->clockIn($user, $user);
        $this->assertSame(TimeClockState::Working, $service->currentState($user));

        $service->startBreak($user, $user);
        $this->assertSame(TimeClockState::OnBreak, $service->currentState($user));

        $service->endBreak($user, $user);
        $this->assertSame(TimeClockState::Working, $service->currentState($user));

        $service->clockOut($user, $user);
        $this->assertSame(TimeClockState::NotClockedIn, $service->currentState($user));
    }

    public function test_illegal_transitions_are_rejected(): void
    {
        $user = User::factory()->create();
        $service = new TimeClockService;

        $this->expectException(RuntimeException::class);
        $service->startBreak($user, $user);
    }

    public function test_clock_out_while_on_break_inserts_implicit_break_end(): void
    {
        $user = User::factory()->create();
        $service = new TimeClockService;

        $service->clockIn($user, $user);
        $service->startBreak($user, $user);
        $service->clockOut($user, $user);

        $types = $user->fresh()->timeEntries()->orderBy('id')->pluck('type');

        $this->assertSame(
            [TimeEntryType::Come, TimeEntryType::BreakStart, TimeEntryType::BreakEnd, TimeEntryType::Go],
            $types->all(),
        );
    }
}
