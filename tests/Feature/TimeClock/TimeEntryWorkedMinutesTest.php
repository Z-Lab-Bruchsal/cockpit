<?php

namespace Tests\Feature\TimeClock;

use App\Enums\TimeEntryType;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\WorkTime\TimeClockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeEntryWorkedMinutesTest extends TestCase
{
    use RefreshDatabase;

    private function punch(User $user, TimeEntryType $type, string $time): TimeEntry
    {
        return TimeEntry::create([
            'user_id' => $user->id,
            'type' => $type,
            'happened_at' => $time,
            'recorded_by_user_id' => $user->id,
        ]);
    }

    public function test_full_day_cycle_computes_worked_minutes_on_segment_closing_entries(): void
    {
        $user = User::factory()->create();

        $come = $this->punch($user, TimeEntryType::Come, '2026-07-20 08:00:00');
        $breakStart = $this->punch($user, TimeEntryType::BreakStart, '2026-07-20 12:00:00');
        $breakEnd = $this->punch($user, TimeEntryType::BreakEnd, '2026-07-20 12:30:00');
        $go = $this->punch($user, TimeEntryType::Go, '2026-07-20 16:00:00');

        $this->assertNull($come->fresh()->worked_minutes);
        $this->assertSame(240, $breakStart->fresh()->worked_minutes);
        $this->assertNull($breakEnd->fresh()->worked_minutes);
        $this->assertSame(210, $go->fresh()->worked_minutes);
    }

    public function test_clock_out_while_on_break_gives_zero_minutes_for_the_go_entry(): void
    {
        $user = User::factory()->create();
        $service = new TimeClockService;

        $service->clockIn($user, $user, now()->setTime(8, 0));
        $service->startBreak($user, $user, now()->setTime(12, 0));
        $goEntry = $service->clockOut($user, $user, now()->setTime(12, 15));

        $breakEndEntry = $user->timeEntries()->where('type', TimeEntryType::BreakEnd)->first();

        $this->assertSame(0, $goEntry->fresh()->worked_minutes);
        $this->assertNull($breakEndEntry->worked_minutes);
    }

    public function test_correcting_happened_at_recomputes_worked_minutes_for_the_entry_and_its_successor(): void
    {
        $user = User::factory()->create();

        $come = $this->punch($user, TimeEntryType::Come, '2026-07-20 08:00:00');
        $breakStart = $this->punch($user, TimeEntryType::BreakStart, '2026-07-20 12:00:00');
        $breakEnd = $this->punch($user, TimeEntryType::BreakEnd, '2026-07-20 12:30:00');
        $go = $this->punch($user, TimeEntryType::Go, '2026-07-20 16:00:00');

        $this->assertSame(240, $breakStart->fresh()->worked_minutes);
        $this->assertSame(210, $go->fresh()->worked_minutes);

        // Move break_start to 13:00, i.e. *after* break_end (12:30). This is
        // an inconsistent timeline (a break ending before it starts), which
        // the system doesn't forbid — it just recomputes adjacency-based
        // durations from whatever order the timestamps now imply.
        $breakStart->update(['happened_at' => '2026-07-20 13:00:00']);

        // break_start's new nearest predecessor is break_end (12:30), a
        // valid segment-opening type, so it gets a (now nonsensical, but
        // mechanically correct) 30-minute duration.
        $this->assertSame(30, $breakStart->fresh()->worked_minutes);

        // break_start (13:00) is now "go"'s nearest predecessor, but
        // break_start isn't a valid segment-opening type, so "go" degrades
        // to null rather than showing a misleading number.
        $this->assertNull($go->fresh()->worked_minutes);

        $this->assertNotNull($come);
        $this->assertNotNull($breakEnd);
    }

    public function test_deleting_an_entry_recomputes_the_following_entrys_worked_minutes(): void
    {
        $user = User::factory()->create();

        $this->punch($user, TimeEntryType::Come, '2026-07-20 08:00:00');
        $breakStart = $this->punch($user, TimeEntryType::BreakStart, '2026-07-20 12:00:00');
        $breakEnd = $this->punch($user, TimeEntryType::BreakEnd, '2026-07-20 12:30:00');
        $go = $this->punch($user, TimeEntryType::Go, '2026-07-20 16:00:00');

        $this->assertSame(210, $go->fresh()->worked_minutes);

        // Deleting the break entirely (both break_start and break_end)
        // should make "go" measure the whole day against "come" instead.
        $breakStart->delete();
        $breakEnd->delete();

        $this->assertSame(480, $go->fresh()->worked_minutes);
    }
}
