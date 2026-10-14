<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShieldSeeder::class);
    }

    public function test_pages_render_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Z-Lab-Boss');
        $role = Role::findByName('Z-Lab-Crew');

        $this->actingAs($user);

        $this->get('/cockpit/shield/roles')->assertOk();
        $this->get('/cockpit/shield/roles/create')->assertOk();
        $this->get("/cockpit/shield/roles/{$role->id}/edit")->assertOk();
    }

    public function test_group_edit_page_renders_with_users_relation_manager(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Z-Lab-Boss');
        $group = Group::factory()->create();

        $this->actingAs($user);

        $this->get("/cockpit/groups/{$group->id}/edit")->assertOk();
    }
}
