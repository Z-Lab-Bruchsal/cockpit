<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SsoUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filament-oauth2.clientId' => 'cockpit',
            'filament-oauth2.clientSecret' => 'secret',
            'filament-oauth2.urlAuthorize' => 'https://sso.example.test/auth',
            'filament-oauth2.urlAccessToken' => 'https://sso.example.test/token',
            'filament-oauth2.urlResourceOwnerDetails' => 'https://sso.example.test/userinfo',
            'filament-oauth2.urlLogout' => 'https://sso.example.test/logout',
            'filament-oauth2.urlAfterlogout' => '/',
        ]);
    }

    public function test_login_redirects_to_the_oauth2_server(): void
    {
        $this->get('/cockpit/login')
            ->assertRedirectContains('https://sso.example.test/auth')
            ->assertSessionHas('filament-oauth2.state');
    }

    public function test_password_reset_is_not_offered(): void
    {
        $this->get('/cockpit/password-reset/request')->assertNotFound();
    }

    public function test_callback_with_foreign_state_is_rejected(): void
    {
        $this->withSession(['filament-oauth2.state' => 'expected-state'])
            ->get(route('filament.admin.filament-oauth2.handleCallback', ['code' => 'code', 'state' => 'other-state']))
            ->assertForbidden();

        $this->assertGuest();
    }

    public function test_callback_with_error_of_the_oauth2_server_is_rejected(): void
    {
        $this->withSession(['filament-oauth2.state' => 'expected-state'])
            ->get(route('filament.admin.filament-oauth2.handleCallback', ['error' => 'access_denied', 'state' => 'expected-state']))
            ->assertForbidden();

        $this->assertGuest();
    }

    public function test_logout_ends_the_session_at_the_oauth2_server(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('filament.admin.filament-oauth2.handleLogout'))
            ->assertRedirectContains('https://sso.example.test/logout?client_id=cockpit');

        $this->assertGuest();
    }
}
