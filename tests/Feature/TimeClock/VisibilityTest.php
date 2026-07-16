<?php

namespace Tests\Feature\TimeClock;

use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_always_sees_themselves(): void
    {
        $user = User::factory()->create();

        $this->assertSame([$user->id], $user->visibleUserIds());
        $this->assertTrue($user->canManageTimeEntriesFor($user));
    }

    public function test_role_attached_to_group_makes_group_members_visible(): void
    {
        $group = Group::factory()->create();
        $role = Role::factory()->create();
        $role->groups()->attach($group);

        $viewer = User::factory()->create();
        $viewer->roles()->attach($role);

        $member = User::factory()->create();
        $member->groups()->attach($group);

        $unrelated = User::factory()->create();

        $visibleIds = $viewer->visibleUserIds();

        $this->assertContains($viewer->id, $visibleIds);
        $this->assertContains($member->id, $visibleIds);
        $this->assertNotContains($unrelated->id, $visibleIds);

        $this->assertTrue($viewer->canManageTimeEntriesFor($member));
        $this->assertFalse($viewer->canManageTimeEntriesFor($unrelated));
    }

    public function test_group_membership_without_matching_role_is_not_visible(): void
    {
        $group = Group::factory()->create();

        $viewer = User::factory()->create();

        $member = User::factory()->create();
        $member->groups()->attach($group);

        $this->assertFalse($viewer->canManageTimeEntriesFor($member));
    }
}
