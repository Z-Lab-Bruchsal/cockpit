<?php

namespace Tests\Feature\TimeClock;

use App\Enums\TimeEntryType;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeEntryAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_time_entry_writes_a_created_audit_row(): void
    {
        $user = User::factory()->create();

        $entry = TimeEntry::create([
            'user_id' => $user->id,
            'type' => TimeEntryType::Come,
            'happened_at' => now(),
            'recorded_by_user_id' => $user->id,
        ]);

        $this->assertSame(1, $entry->audits()->count());
        $this->assertSame('created', $entry->audits()->first()->action);
    }

    public function test_updating_happened_at_writes_one_audit_row_with_old_and_new_value(): void
    {
        $user = User::factory()->create();
        $entry = TimeEntry::factory()->create([
            'user_id' => $user->id,
            'happened_at' => '2026-07-16 08:00:00',
        ]);

        $entry->update(['happened_at' => '2026-07-16 08:15:00']);

        $audit = $entry->audits()->where('action', 'updated')->where('field', 'happened_at')->first();

        $this->assertNotNull($audit);
        $this->assertStringContainsString('08:00:00', $audit->old_value);
        $this->assertStringContainsString('08:15:00', $audit->new_value);
    }

    public function test_updating_multiple_fields_writes_one_audit_row_per_field(): void
    {
        $user = User::factory()->create();
        $entry = TimeEntry::factory()->create([
            'user_id' => $user->id,
            'type' => TimeEntryType::Come,
            'note' => null,
        ]);

        $entry->update(['type' => TimeEntryType::Go, 'note' => 'Nachtrag']);

        $this->assertSame(3, $entry->audits()->count());
        $this->assertSame(2, $entry->audits()->where('action', 'updated')->count());
    }

    public function test_deleting_a_time_entry_writes_a_deleted_snapshot_row(): void
    {
        $user = User::factory()->create();
        $entry = TimeEntry::factory()->create(['user_id' => $user->id]);

        $entry->delete();

        $this->assertSame(2, $entry->audits()->count());
        $this->assertSame('deleted', $entry->audits()->latest('id')->first()->action);
    }
}
