<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_in_the_reset_mail_opens_the_reset_page(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('ViewAny:Todo'));

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $user->email])
            ->call('request');

        $url = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$url): bool {
            $url = $notification->url;

            return true;
        });

        $this->get($url)->assertOk();
    }
}
