<?php

namespace Tests\Feature\Reports;

use App\Models\Booking;
use App\Models\User;
use App\Services\Reports\BookingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private BookingReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new BookingReportService;
    }

    public function test_format_minutes_renders_hours_and_minutes(): void
    {
        $this->assertSame('0h 00min', BookingReportService::formatMinutes(null));
        $this->assertSame('1h 05min', BookingReportService::formatMinutes(65));
        $this->assertSame('12h 00min', BookingReportService::formatMinutes(720));
    }

    public function test_monthly_bookings_only_includes_the_given_month(): void
    {
        $user = User::factory()->create();

        $inMonth = Booking::factory()->create([
            'user_id' => $user->id,
            'start_at' => '2026-03-05 08:00:00',
            'end_at' => '2026-03-05 16:00:00',
        ]);
        Booking::factory()->create([
            'user_id' => $user->id,
            'start_at' => '2026-04-01 08:00:00',
            'end_at' => '2026-04-01 09:00:00',
        ]);

        $bookings = $this->service->monthlyBookings($user, 2026, 3);

        $this->assertCount(1, $bookings);
        $this->assertSame($inMonth->id, $bookings->first()->id);
        $this->assertSame(480, $this->service->monthlyTotalMinutes($user, 2026, 3));
    }

    public function test_yearly_monthly_totals_buckets_by_month(): void
    {
        $user = User::factory()->create();

        Booking::factory()->create([
            'user_id' => $user->id,
            'start_at' => '2026-01-10 08:00:00',
            'end_at' => '2026-01-10 10:00:00',
        ]);
        Booking::factory()->create([
            'user_id' => $user->id,
            'start_at' => '2026-06-10 08:00:00',
            'end_at' => '2026-06-10 12:00:00',
        ]);

        $totals = $this->service->yearlyMonthlyTotals($user, 2026);

        $this->assertSame(120, $totals[1]);
        $this->assertSame(240, $totals[6]);
        $this->assertSame(0, $totals[2]);
        $this->assertSame(360, $this->service->yearlyTotalMinutes($user, 2026));
    }

    public function test_monthly_totals_for_all_users_groups_by_user(): void
    {
        $userA = User::factory()->create(['name' => 'Anna']);
        $userB = User::factory()->create(['name' => 'Bert']);

        Booking::factory()->create([
            'user_id' => $userA->id,
            'start_at' => '2026-03-05 08:00:00',
            'end_at' => '2026-03-05 10:00:00',
        ]);
        Booking::factory()->create([
            'user_id' => $userB->id,
            'start_at' => '2026-03-06 08:00:00',
            'end_at' => '2026-03-06 09:00:00',
        ]);

        $totals = $this->service->monthlyTotalsForAllUsers(2026, 3)->keyBy(fn ($row) => $row['user']->id);

        $this->assertSame(120, $totals[$userA->id]['minutes']);
        $this->assertSame(60, $totals[$userB->id]['minutes']);
    }

    public function test_yearly_totals_for_all_users_includes_users_without_bookings(): void
    {
        $userWithBookings = User::factory()->create();
        $userWithoutBookings = User::factory()->create();

        Booking::factory()->create([
            'user_id' => $userWithBookings->id,
            'start_at' => '2026-05-01 08:00:00',
            'end_at' => '2026-05-01 09:30:00',
        ]);

        $totals = $this->service->yearlyTotalsForAllUsers(2026)->keyBy(fn ($row) => $row['user']->id);

        $this->assertSame(90, $totals[$userWithBookings->id]['minutes']);
        $this->assertSame(0, $totals[$userWithoutBookings->id]['minutes']);
    }
}
