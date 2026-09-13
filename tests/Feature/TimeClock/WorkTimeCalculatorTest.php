<?php

namespace Tests\Feature\TimeClock;

use App\Enums\TimeEntryType;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\WorkTime\WorkTimeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkTimeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function punch(User $user, TimeEntryType $type, string $time): void
    {
        TimeEntry::create([
            'user_id' => $user->id,
            'type' => $type,
            'happened_at' => $time,
            'recorded_by_user_id' => $user->id,
        ]);
    }

    public function test_no_warning_when_worked_exactly_six_hours(): void
    {
        $user = User::factory()->create();
        $this->punch($user, TimeEntryType::Come, '2026-07-20 08:00:00');
        $this->punch($user, TimeEntryType::Go, '2026-07-20 14:00:00');

        $warnings = (new WorkTimeCalculator)->complianceWarnings($user, Carbon::parse('2026-07-20'));

        $this->assertSame([], $warnings);
    }

    public function test_warning_when_over_six_hours_without_sufficient_break(): void
    {
        $user = User::factory()->create();
        $this->punch($user, TimeEntryType::Come, '2026-07-20 08:00:00');
        $this->punch($user, TimeEntryType::Go, '2026-07-20 14:01:00');

        $warnings = (new WorkTimeCalculator)->complianceWarnings($user, Carbon::parse('2026-07-20'));

        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('30-Minuten-Pause', $warnings[0]);
    }

    public function test_no_warning_when_six_hour_threshold_break_taken(): void
    {
        $user = User::factory()->create();
        $this->punch($user, TimeEntryType::Come, '2026-07-20 08:00:00');
        $this->punch($user, TimeEntryType::BreakStart, '2026-07-20 12:00:00');
        $this->punch($user, TimeEntryType::BreakEnd, '2026-07-20 12:30:00');
        $this->punch($user, TimeEntryType::Go, '2026-07-20 14:31:00');

        $warnings = (new WorkTimeCalculator)->complianceWarnings($user, Carbon::parse('2026-07-20'));

        $this->assertSame([], $warnings);
    }

    public function test_escalated_warning_at_nine_hour_threshold(): void
    {
        $user = User::factory()->create();
        $this->punch($user, TimeEntryType::Come, '2026-07-20 08:00:00');
        $this->punch($user, TimeEntryType::BreakStart, '2026-07-20 12:00:00');
        $this->punch($user, TimeEntryType::BreakEnd, '2026-07-20 12:30:00');
        $this->punch($user, TimeEntryType::Go, '2026-07-20 17:31:00');

        $warnings = (new WorkTimeCalculator)->complianceWarnings($user, Carbon::parse('2026-07-20'));

        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('45-Minuten-Pause', $warnings[0]);
    }

    public function test_two_short_breaks_do_not_qualify(): void
    {
        $user = User::factory()->create();
        $this->punch($user, TimeEntryType::Come, '2026-07-20 08:00:00');
        $this->punch($user, TimeEntryType::BreakStart, '2026-07-20 10:00:00');
        $this->punch($user, TimeEntryType::BreakEnd, '2026-07-20 10:10:00');
        $this->punch($user, TimeEntryType::BreakStart, '2026-07-20 12:00:00');
        $this->punch($user, TimeEntryType::BreakEnd, '2026-07-20 12:10:00');
        $this->punch($user, TimeEntryType::Go, '2026-07-20 16:00:00');

        $qualifying = (new WorkTimeCalculator)->qualifyingBreakMinutes($user, Carbon::parse('2026-07-20'));

        $this->assertSame(0, $qualifying);
    }

    public function test_one_twenty_minute_break_qualifies(): void
    {
        $user = User::factory()->create();
        $this->punch($user, TimeEntryType::Come, '2026-07-20 08:00:00');
        $this->punch($user, TimeEntryType::BreakStart, '2026-07-20 12:00:00');
        $this->punch($user, TimeEntryType::BreakEnd, '2026-07-20 12:20:00');
        $this->punch($user, TimeEntryType::Go, '2026-07-20 16:00:00');

        $qualifying = (new WorkTimeCalculator)->qualifyingBreakMinutes($user, Carbon::parse('2026-07-20'));

        $this->assertSame(20, $qualifying);
    }

    public function test_day_grouping_uses_business_local_time_not_utc(): void
    {
        // 2026-07-20 22:30:00 UTC is 2026-07-21 00:30:00 in Europe/Berlin (CEST, UTC+2).
        // It must be grouped under the business day 2026-07-21, not 2026-07-20.
        $user = User::factory()->create();
        $this->punch($user, TimeEntryType::Come, '2026-07-20 22:30:00');
        $this->punch($user, TimeEntryType::Go, '2026-07-20 23:00:00');

        $calculator = new WorkTimeCalculator;

        $this->assertSame(0, $calculator->workedMinutes($user, Carbon::parse('2026-07-20')));
        $this->assertSame(30, $calculator->workedMinutes($user, Carbon::parse('2026-07-21')));
    }

}
