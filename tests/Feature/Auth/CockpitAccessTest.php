<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CockpitAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_user_without_role_or_permission_cannot_enter_the_cockpit(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/cockpit')
            ->assertForbidden();
    }

    public function test_user_with_role_can_enter_the_cockpit(): void
    {
        $this->seed(ShieldSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Z-Lab-Crew');

        $this->actingAs($user)
            ->get('/cockpit')
            ->assertOk();
    }

    public function test_user_with_direct_permission_can_enter_the_cockpit(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('ViewAny:Todo'));

        $this->actingAs($user)
            ->get('/cockpit')
            ->assertOk();
    }
}
