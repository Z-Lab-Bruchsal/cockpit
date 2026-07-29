<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_render_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();
        $role->groups()->attach($group);

        $this->actingAs($user);

        $this->get('/roles')->assertOk();
        $this->get('/roles/create')->assertOk();
        $this->get("/roles/{$role->id}/edit")->assertOk();
    }

    public function test_group_edit_page_renders_with_new_roles_relation_manager(): void
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();

        $this->actingAs($user);

        $this->get("/groups/{$group->id}/edit")->assertOk();
    }
}
