<?php

namespace Tests\Feature\TimeClock;

use App\Enums\TimeClockState;
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

        $service->clockOut($user, $user);
        $this->assertSame(TimeClockState::NotClockedIn, $service->currentState($user));
    }

    public function test_illegal_transitions_are_rejected(): void
    {
        $user = User::factory()->create();
        $service = new TimeClockService;

        $this->expectException(RuntimeException::class);
        $service->clockOut($user, $user);
    }

    public function test_clocking_in_records_a_booking_with_a_start_time(): void
    {
        $user = User::factory()->create();
        $service = new TimeClockService;

        $booking = $service->clockIn($user, $user);

        $this->assertNotNull($booking->start_at);
        $this->assertNull($booking->end_at);
    }

    public function test_clocking_out_sets_the_end_time_on_the_open_booking(): void
    {
        $user = User::factory()->create();
        $service = new TimeClockService;

        $opened = $service->clockIn($user, $user);
        $closed = $service->clockOut($user, $user);

        $this->assertSame($opened->id, $closed->id);
        $this->assertNotNull($closed->fresh()->end_at);
    }
}
