<?php

namespace Tests\Feature\TimeClock;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingWorkedMinutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_worked_minutes_is_computed_from_start_and_end(): void
    {
        $user = User::factory()->create();

        $booking = Booking::create([
            'user_id' => $user->id,
            'start_at' => '2026-07-20 08:00:00',
            'end_at' => '2026-07-20 16:00:00',
            'recorded_by_user_id' => $user->id,
        ]);

        $this->assertSame(480, $booking->fresh()->worked_minutes);
    }

    public function test_worked_minutes_is_null_while_the_booking_is_still_open(): void
    {
        $user = User::factory()->create();

        $booking = Booking::create([
            'user_id' => $user->id,
            'start_at' => '2026-07-20 08:00:00',
            'recorded_by_user_id' => $user->id,
        ]);

        $this->assertNull($booking->fresh()->worked_minutes);
    }

    public function test_correcting_start_or_end_time_recomputes_worked_minutes(): void
    {
        $user = User::factory()->create();

        $booking = Booking::create([
            'user_id' => $user->id,
            'start_at' => '2026-07-20 08:00:00',
            'end_at' => '2026-07-20 16:00:00',
            'recorded_by_user_id' => $user->id,
        ]);

        $this->assertSame(480, $booking->fresh()->worked_minutes);

        $booking->update(['end_at' => '2026-07-20 17:00:00']);

        $this->assertSame(540, $booking->fresh()->worked_minutes);
    }

    public function test_clearing_the_end_time_clears_worked_minutes(): void
    {
        $user = User::factory()->create();

        $booking = Booking::create([
            'user_id' => $user->id,
            'start_at' => '2026-07-20 08:00:00',
            'end_at' => '2026-07-20 16:00:00',
            'recorded_by_user_id' => $user->id,
        ]);

        $booking->update(['end_at' => null]);

        $this->assertNull($booking->fresh()->worked_minutes);
    }
}
