<?php

namespace Tests\Feature\TimeClock;

use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Tables\BookingsTable;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class BookingsTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Thursday, so this_week/this_month boundaries are unambiguous.
        Carbon::setTestNow(Carbon::parse('2026-07-16 12:00:00', config('app.business_timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function periodKeyFor(Carbon $startAt): string
    {
        $method = new ReflectionMethod(BookingsTable::class, 'periodKeyFor');
        $method->setAccessible(true);

        return $method->invoke(null, $startAt);
    }

    public function test_period_key_buckets_are_computed_correctly(): void
    {
        $timezone = config('app.business_timezone');

        $this->assertSame('today', $this->periodKeyFor(Carbon::parse('2026-07-16 08:00:00', $timezone)));
        $this->assertSame('yesterday', $this->periodKeyFor(Carbon::parse('2026-07-15 08:00:00', $timezone)));
        // Monday of the same week (2026-07-13), not today/yesterday.
        $this->assertSame('this_week', $this->periodKeyFor(Carbon::parse('2026-07-13 08:00:00', $timezone)));
        // Earlier in the same month, but before this week started.
        $this->assertSame('this_month', $this->periodKeyFor(Carbon::parse('2026-07-02 08:00:00', $timezone)));
        // Last month entirely.
        $this->assertSame('older', $this->periodKeyFor(Carbon::parse('2026-06-15 08:00:00', $timezone)));
    }

    public function test_period_filter_narrows_to_today(): void
    {
        $user = User::factory()->create();
        $timezone = config('app.business_timezone');

        $todayBooking = Booking::create([
            'user_id' => $user->id,
            'start_at' => Carbon::parse('2026-07-16 08:00:00', $timezone)->setTimezone('UTC'),
            'recorded_by_user_id' => $user->id,
        ]);
        $lastMonthBooking = Booking::create([
            'user_id' => $user->id,
            'start_at' => Carbon::parse('2026-06-15 08:00:00', $timezone)->setTimezone('UTC'),
            'recorded_by_user_id' => $user->id,
        ]);

        $this->actingAs($user);

        Livewire::test(ListBookings::class)
            ->filterTable('period', ['value' => 'today'])
            ->assertCanSeeTableRecords([$todayBooking])
            ->assertCanNotSeeTableRecords([$lastMonthBooking]);
    }

    public function test_table_groups_entries_by_period_and_shows_todays_group(): void
    {
        $user = User::factory()->create();
        $timezone = config('app.business_timezone');

        Booking::create([
            'user_id' => $user->id,
            'start_at' => Carbon::parse('2026-07-16 08:00:00', $timezone)->setTimezone('UTC'),
            'recorded_by_user_id' => $user->id,
        ]);

        $this->actingAs($user);

        Livewire::test(ListBookings::class)
            ->assertSee('Heute');
    }
}
