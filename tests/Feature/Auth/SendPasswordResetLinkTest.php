<?php

namespace Tests\Feature\Auth;

use App\Filament\Actions\SendPasswordResetLinkAction;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SendPasswordResetLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();
    }

    private function actingAsUserManager(): User
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(
            Permission::findOrCreate('ViewAny:User'),
            Permission::findOrCreate('View:User'),
            Permission::findOrCreate('Update:User'),
        );
        $this->actingAs($admin);

        return $admin;
    }

    private function cockpitUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('ViewAny:Todo'));

        return $user;
    }

    public function test_reset_link_can_be_sent_from_the_edit_page_and_opens_the_reset_page(): void
    {
        $this->actingAsUserManager();
        $user = $this->cockpitUser();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->callAction(SendPasswordResetLinkAction::class)
            ->assertNotified();

        $url = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$url): bool {
            $url = $notification->url;

            return true;
        });

        auth()->logout();
        $this->get($url)->assertOk();
    }

    public function test_reset_link_can_be_sent_from_the_users_table(): void
    {
        $this->actingAsUserManager();
        $user = $this->cockpitUser();

        Livewire::test(ListUsers::class)
            ->callAction(TestAction::make('sendPasswordResetLink')->table($user))
            ->assertNotified();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_action_is_hidden_for_users_without_cockpit_access(): void
    {
        $this->actingAsUserManager();
        $customer = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $customer->getRouteKey()])
            ->assertActionHidden(SendPasswordResetLinkAction::class);

        Notification::assertNothingSent();
    }

    public function test_action_requires_update_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::findOrCreate('ViewAny:User'), Permission::findOrCreate('View:User'));
        $this->actingAs($viewer);
        $user = $this->cockpitUser();

        Livewire::test(ListUsers::class)
            ->assertActionHidden(TestAction::make('sendPasswordResetLink')->table($user));

        Notification::assertNothingSent();
    }
}
