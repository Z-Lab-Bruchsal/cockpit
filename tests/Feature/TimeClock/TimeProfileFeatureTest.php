<?php

namespace Tests\Feature\TimeClock;

use App\Models\Role;
use App\Models\TimeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeProfileFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_time_profile_pages_render(): void
    {
        $user = User::factory()->create();
        $profile = TimeProfile::factory()->create();

        $this->actingAs($user);

        $this->get('/time-profiles')->assertOk();
        $this->get('/time-profiles/create')->assertOk();
        $this->get("/time-profiles/{$profile->id}/edit")->assertOk();
    }

    public function test_user_edit_page_renders_with_time_profile_assignments_relation_manager(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($admin);

        $this->get("/users/{$target->id}/edit")->assertOk();
    }

    public function test_settings_page_is_only_accessible_to_the_zeiterfassung_admin_role(): void
    {
        $regularUser = User::factory()->create();
        $this->actingAs($regularUser);
        $this->get('/work-time-settings-page')->assertForbidden();

        $adminRole = Role::where('name', 'zeiterfassung-admin')->firstOrFail();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        $this->actingAs($admin);
        $this->get('/work-time-settings-page')->assertOk();
    }
}
