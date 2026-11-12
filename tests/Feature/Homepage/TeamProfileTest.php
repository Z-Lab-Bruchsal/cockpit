<?php

namespace Tests\Feature\Homepage;

use App\Enums\TeamArea;
use App\Filament\Pages\TeamPage;
use App\Models\Interest;
use App\Models\TeamProfile;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    private function actingAsTeamManager(): User
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('Homepage:Team'));
        $this->actingAs($admin);

        return $admin;
    }

    public function test_existing_team_profiles_are_migrated_with_their_photos_and_interests(): void
    {
        $user = User::factory()->create(['name' => 'Paula Beispiel']);
        $interest = Interest::factory()->create();

        DB::table('users')->where('id', $user->id)->update([
            'team_area' => TeamArea::Freiwilligendienst->value,
            'team_visible' => true,
            'team_photo_path' => 'team/paula.webp',
            'team_sort' => 4,
        ]);
        DB::table('interest_user')->insert(['user_id' => $user->id, 'interest_id' => $interest->id]);

        $migration = require database_path('migrations/2026_11_12_163001_copy_existing_team_profiles.php');
        $migration->up();

        $profile = $user->teamProfile()->firstOrFail();
        $this->assertSame('Paula Beispiel', $profile->displayName());
        $this->assertSame(TeamArea::Freiwilligendienst, $profile->team_area);
        $this->assertTrue($profile->team_visible);
        $this->assertSame('team/paula.webp', $profile->team_photo_path);
        $this->assertSame(4, $profile->team_sort);
        $this->assertTrue($profile->interests->contains($interest));
    }

    public function test_team_page_requires_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(TeamPage::getUrl())->assertForbidden();
    }

    public function test_team_page_lists_only_team_members(): void
    {
        $this->actingAsTeamManager();
        $member = TeamProfile::factory()->create();
        $hiddenMember = TeamProfile::factory()->create(['team_visible' => false]);
        $removedProfile = TeamProfile::factory()->create(['team_area' => null]);

        $this->get(TeamPage::getUrl())->assertOk();

        Livewire::test(TeamPage::class)
            ->assertCanSeeTableRecords([$member, $hiddenMember])
            ->assertCanNotSeeTableRecords([$removedProfile]);
    }

    public function test_user_can_be_added_to_the_team(): void
    {
        $this->actingAsTeamManager();
        TeamProfile::factory()->create(['team_sort' => 4]);
        $user = User::factory()->create();

        Livewire::test(TeamPage::class)
            ->callAction(TestAction::make('addMember')->table(), ['user_id' => $user->id, 'team_area' => TeamArea::Ehrenamt->value])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $profile = $user->teamProfile()->firstOrFail();
        $this->assertSame(TeamArea::Ehrenamt, $profile->team_area);
        $this->assertFalse($profile->team_visible);
        $this->assertSame(5, $profile->team_sort);
    }

    public function test_standalone_profile_can_be_created_without_an_account(): void
    {
        $this->actingAsTeamManager();

        Livewire::test(TeamPage::class)
            ->callAction(TestAction::make('addStandaloneProfile')->table(), [
                'name' => 'Paula Beispiel',
                'team_area' => TeamArea::Freiwilligendienst->value,
            ])
            ->assertHasNoActionErrors();

        $profile = TeamProfile::query()->where('name', 'Paula Beispiel')->firstOrFail();
        $this->assertNull($profile->user_id);
        $this->assertFalse($profile->team_visible);
        $this->assertSame(TeamArea::Freiwilligendienst, $profile->team_area);

        Livewire::test(TeamPage::class)
            ->callAction(TestAction::make('edit')->table($profile), [
                'name' => 'Paula Beispiel',
                'team_visible' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertTrue($profile->refresh()->team_visible);
        $this->assertFalse($profile->isContactableViaHomepage());
    }

    public function test_adding_requires_an_area(): void
    {
        $this->actingAsTeamManager();
        $user = User::factory()->create();

        Livewire::test(TeamPage::class)
            ->callAction(TestAction::make('addMember')->table(), ['user_id' => $user->id, 'team_area' => null])
            ->assertHasActionErrors(['team_area' => 'required']);
    }

    public function test_team_profile_can_be_edited(): void
    {
        $this->actingAsTeamManager();
        $member = TeamProfile::factory()->for(User::factory())->create(['team_visible' => false]);
        $interest = Interest::factory()->create();

        Livewire::test(TeamPage::class)
            ->callAction(TestAction::make('edit')->table($member), [
                'team_visible' => true,
                'team_area' => TeamArea::Leitung->value,
                'team_role' => 'Leitung',
                'team_bio' => 'Ich leite das Z-LAB.',
                'team_contactable' => false,
                'interests' => [$interest->id],
            ])
            ->assertHasNoActionErrors();

        $member->refresh();
        $this->assertTrue($member->team_visible);
        $this->assertSame(TeamArea::Leitung, $member->team_area);
        $this->assertSame('Leitung', $member->team_role);
        $this->assertSame('Ich leite das Z-LAB.', $member->team_bio);
        $this->assertFalse($member->team_contactable);
        $this->assertTrue($member->interests->contains($interest));
    }

    public function test_team_members_can_be_reordered(): void
    {
        $this->actingAsTeamManager();
        $first = TeamProfile::factory()->create(['team_sort' => 1]);
        $second = TeamProfile::factory()->create(['team_sort' => 2]);

        Livewire::test(TeamPage::class)
            ->call('reorderTable', [$second->getKey(), $first->getKey()]);

        $this->assertSame(1, $second->refresh()->team_sort);
        $this->assertSame(2, $first->refresh()->team_sort);
    }

    public function test_member_can_be_removed_from_the_team_and_keeps_the_profile(): void
    {
        $this->actingAsTeamManager();
        $member = TeamProfile::factory()->for(User::factory())->create(['team_role' => 'Jugendleiterin']);

        Livewire::test(TeamPage::class)
            ->callAction(TestAction::make('removeFromTeam')->table($member));

        $member->refresh();
        $this->assertNull($member->team_area);
        $this->assertFalse($member->team_visible);
        $this->assertSame('Jugendleiterin', $member->team_role);
    }

    public function test_profile_page_no_longer_contains_the_team_profile(): void
    {
        $member = User::factory()->create();
        $member->givePermissionTo(Permission::findOrCreate('ViewAny:Todo'));
        $this->actingAs($member);

        $this->get('/cockpit/profile')->assertOk()->assertDontSee('Mein Teamprofil');
    }
}
