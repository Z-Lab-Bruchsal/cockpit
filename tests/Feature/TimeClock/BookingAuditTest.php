<?php

namespace Tests\Feature\TimeClock;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_booking_writes_a_created_audit_row(): void
    {
        $user = User::factory()->create();

        $booking = Booking::create([
            'user_id' => $user->id,
            'start_at' => now(),
            'recorded_by_user_id' => $user->id,
        ]);

        $this->assertSame(1, $booking->audits()->count());
        $this->assertSame('created', $booking->audits()->first()->action);
    }

    public function test_updating_start_at_writes_one_audit_row_with_old_and_new_value(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'start_at' => '2026-07-16 08:00:00',
        ]);

        $booking->update(['start_at' => '2026-07-16 08:15:00']);

        $audit = $booking->audits()->where('action', 'updated')->where('field', 'start_at')->first();

        $this->assertNotNull($audit);
        $this->assertStringContainsString('08:00:00', $audit->old_value);
        $this->assertStringContainsString('08:15:00', $audit->new_value);
    }

    public function test_updating_multiple_fields_writes_one_audit_row_per_field(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'note' => null,
        ]);

        $booking->update(['end_at' => now(), 'note' => 'Nachtrag']);

        $this->assertSame(3, $booking->audits()->count());
        $this->assertSame(2, $booking->audits()->where('action', 'updated')->count());
    }

    public function test_deleting_a_booking_writes_a_deleted_snapshot_row(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id]);

        $booking->delete();

        $this->assertSame(2, $booking->audits()->count());
        $this->assertSame('deleted', $booking->audits()->latest('id')->first()->action);
    }
}
