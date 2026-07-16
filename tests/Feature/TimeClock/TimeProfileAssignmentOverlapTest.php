<?php

namespace Tests\Feature\TimeClock;

use App\Models\TimeProfileAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TimeProfileAssignmentOverlapTest extends TestCase
{
    use RefreshDatabase;

    public function test_overlapping_date_range_is_detected(): void
    {
        $user = User::factory()->create();
        TimeProfileAssignment::factory()->create([
            'user_id' => $user->id,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-06-30',
        ]);

        $overlaps = TimeProfileAssignment::overlapsExisting(
            $user->id,
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-12-31'),
        );

        $this->assertTrue($overlaps);
    }

    public function test_non_overlapping_date_range_is_allowed(): void
    {
        $user = User::factory()->create();
        TimeProfileAssignment::factory()->create([
            'user_id' => $user->id,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-06-30',
        ]);

        $overlaps = TimeProfileAssignment::overlapsExisting(
            $user->id,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-12-31'),
        );

        $this->assertFalse($overlaps);
    }

    public function test_open_ended_assignment_conflicts_with_a_later_start(): void
    {
        $user = User::factory()->create();
        TimeProfileAssignment::factory()->create([
            'user_id' => $user->id,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        $overlaps = TimeProfileAssignment::overlapsExisting(
            $user->id,
            Carbon::parse('2027-01-01'),
            null,
        );

        $this->assertTrue($overlaps);
    }

    public function test_editing_an_assignment_ignores_itself(): void
    {
        $user = User::factory()->create();
        $assignment = TimeProfileAssignment::factory()->create([
            'user_id' => $user->id,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-06-30',
        ]);

        $overlaps = TimeProfileAssignment::overlapsExisting(
            $user->id,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-06-30'),
            $assignment->id,
        );

        $this->assertFalse($overlaps);
    }
}
