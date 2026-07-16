<?php

namespace Tests\Feature\TimeClock;

use App\Filament\Resources\TimeEntries\TimeEntryResource;
use App\Models\Group;
use App\Models\Role;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeEntryAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_with_time_clock_widget(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertOk();
    }

    public function test_time_entry_pages_render(): void
    {
        $user = User::factory()->create();
        $entry = TimeEntry::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $this->get('/time-entries')->assertOk();
        $this->get('/time-entries/create')->assertOk();
        $this->get("/time-entries/{$entry->id}/edit")->assertOk();
    }

    public function test_manager_sees_group_members_entries_but_not_unrelated_users(): void
    {
        $group = Group::factory()->create();
        $role = Role::factory()->create();
        $role->groups()->attach($group);

        $manager = User::factory()->create();
        $manager->roles()->attach($role);

        $member = User::factory()->create();
        $member->groups()->attach($group);
        $memberEntry = TimeEntry::factory()->create(['user_id' => $member->id]);

        $unrelated = User::factory()->create();
        $unrelatedEntry = TimeEntry::factory()->create(['user_id' => $unrelated->id]);

        $this->actingAs($manager);

        $visibleIds = TimeEntryResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($memberEntry->id, $visibleIds);
        $this->assertNotContains($unrelatedEntry->id, $visibleIds);
    }
}
