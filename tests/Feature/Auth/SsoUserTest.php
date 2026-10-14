<?php

namespace Tests\Feature\Auth;

use AlexanderGabriel\FilamentOauth2\Pages\Oauth2EditProfile;
use AlexanderGabriel\FilamentOauth2\Pages\Oauth2Login;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class SsoUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_login_page_offers_sso_and_local_login(): void
    {
        $this->withoutVite()->get('/cockpit/login')
            ->assertOk()
            ->assertSee('Mit SSO anmelden')
            ->assertSee(route('filament-oauth2.redirectToOauth2Server'), escape: false);
    }

    public function test_local_user_can_log_in_with_password(): void
    {
        $user = User::factory()->create();

        Livewire::test(Oauth2Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_sso_user_can_not_log_in_with_password(): void
    {
        $user = User::factory()->sso()->create();

        Livewire::test(Oauth2Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_auth_attempt_rejects_sso_users_only(): void
    {
        $localUser = User::factory()->create();
        $ssoUser = User::factory()->sso()->create();

        $this->assertFalse(Auth::attempt(['email' => $ssoUser->email, 'password' => 'password']));
        $this->assertTrue(Auth::attempt(['email' => $localUser->email, 'password' => 'password']));
    }

    public function test_credentials_without_email_do_not_resolve_a_user(): void
    {
        User::factory()->create();

        $this->assertNull(Auth::getProvider()->retrieveByCredentials(['password' => 'password']));
    }

    public function test_sso_user_can_not_request_a_password_reset(): void
    {
        Notification::fake();
        $localUser = User::factory()->create();
        $ssoUser = User::factory()->sso()->create();

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $ssoUser->email])
            ->call('request');

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $localUser->email])
            ->call('request');

        Notification::assertNotSentTo($ssoUser, ResetPasswordNotification::class);
        Notification::assertSentTo($localUser, ResetPasswordNotification::class);
    }

    public function test_sso_user_can_not_reset_password_with_a_token(): void
    {
        $ssoUser = User::factory()->sso()->create();
        $token = Password::broker()->createToken($ssoUser);

        $status = Password::broker()->reset([
            'email' => $ssoUser->email,
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ], fn () => $this->fail('The password of an SSO user must not be reset.'));

        $this->assertSame(Password::INVALID_USER, $status);
    }

    public function test_profile_hides_password_and_locks_name_and_email_for_sso_users(): void
    {
        $this->actingAs(User::factory()->sso()->create());

        Livewire::test(Oauth2EditProfile::class)
            ->assertFormFieldHidden('password')
            ->assertFormFieldDisabled('name')
            ->assertFormFieldDisabled('email');
    }

    public function test_profile_allows_password_change_for_local_users(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Oauth2EditProfile::class)
            ->assertFormFieldVisible('password')
            ->assertFormFieldEnabled('email');
    }

    public function test_local_user_logout_does_not_redirect_to_the_oauth2_server(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('filament-oauth2.handleLogout'))
            ->assertRedirect(Filament::getLoginUrl());

        $this->assertGuest();
    }
}
