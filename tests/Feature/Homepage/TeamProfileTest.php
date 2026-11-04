<?php

namespace Tests\Feature\Homepage;

use App\Enums\TeamArea;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Interest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TeamProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_publish_a_team_profile(): void
    {
        $admin = User::factory()->create();
        foreach (['ViewAny', 'View', 'Update'] as $ability) {
            $admin->givePermissionTo(Permission::findOrCreate("{$ability}:User"));
        }
        $member = User::factory()->create();
        $interest = Interest::factory()->create();

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $member->id])
            ->fillForm([
                'team_visible' => true,
                'team_area' => TeamArea::Leitung->value,
                'team_role' => 'Leitung',
                'team_sort' => 3,
                'team_bio' => 'Ich leite das Z-LAB.',
                'interests' => [$interest->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $member->refresh();
        $this->assertTrue($member->team_visible);
        $this->assertSame(TeamArea::Leitung, $member->team_area);
        $this->assertSame('Leitung', $member->team_role);
        $this->assertSame(3, $member->team_sort);
        $this->assertTrue($member->interests->contains($interest));
    }

    public function test_visible_profile_requires_an_area(): void
    {
        $admin = User::factory()->create();
        foreach (['ViewAny', 'View', 'Update'] as $ability) {
            $admin->givePermissionTo(Permission::findOrCreate("{$ability}:User"));
        }
        $member = User::factory()->create();

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $member->id])
            ->fillForm(['team_visible' => true, 'team_area' => null])
            ->call('save')
            ->assertHasFormErrors(['team_area' => 'required']);
    }

    public function test_team_member_edits_own_profile_but_cannot_publish_it(): void
    {
        $member = User::factory()->create();
        $member->givePermissionTo(Permission::findOrCreate('ViewAny:Todo'));
        $this->actingAs($member);

        $this->get('/cockpit/profile')->assertOk()->assertSee('Mein Teamprofil');

        Livewire::test(EditProfile::class)
            ->assertFormFieldDoesNotExist('team_visible')
            ->assertFormFieldDoesNotExist('team_area')
            ->fillForm([
                'team_role' => 'Jugendleiterin',
                'team_bio' => 'Ich mag Drohnen.',
                'team_contactable' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $member->refresh();
        $this->assertSame('Jugendleiterin', $member->team_role);
        $this->assertSame('Ich mag Drohnen.', $member->team_bio);
        $this->assertFalse($member->team_contactable);
        $this->assertFalse($member->team_visible);
    }
}
