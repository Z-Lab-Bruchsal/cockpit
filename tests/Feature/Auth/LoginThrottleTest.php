<?php

namespace Tests\Feature\Auth;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function cockpitUser(string $email = 'crew@example.com'): User
    {
        $user = User::factory()->create(['email' => $email, 'password' => 'Richtig1Passwort']);
        $user->givePermissionTo(Permission::findOrCreate('ViewAny:Todo'));

        return $user;
    }

    /**
     * Filament's own throttle allows 5 attempts per minute and IP; moving the clock past it
     * keeps these tests focused on the failed-login lock.
     */
    private function attemptLogin(string $email, string $password): Testable
    {
        $this->travel(61)->seconds();

        return Livewire::test(Login::class)
            ->fillForm(['email' => $email, 'password' => $password])
            ->call('authenticate');
    }

    public function test_account_is_locked_after_too_many_failed_attempts(): void
    {
        $this->cockpitUser();

        for ($i = 0; $i < Login::MAX_FAILURES_PER_EMAIL; $i++) {
            $this->attemptLogin('crew@example.com', 'falsch')->assertHasErrors(['data.email']);
        }

        $this->attemptLogin('crew@example.com', 'Richtig1Passwort')
            ->assertHasErrors(['data.email'])
            ->assertSee('Zu viele fehlgeschlagene Anmeldeversuche');

        $this->assertGuest();
    }

    public function test_lock_expires(): void
    {
        $this->cockpitUser();

        for ($i = 0; $i < Login::MAX_FAILURES_PER_EMAIL; $i++) {
            $this->attemptLogin('crew@example.com', 'falsch');
        }

        $this->travel(16)->minutes();

        $this->attemptLogin('crew@example.com', 'Richtig1Passwort')->assertHasNoErrors();
        $this->assertAuthenticated();
    }

    public function test_successful_login_resets_the_failure_counter(): void
    {
        $user = $this->cockpitUser();

        for ($i = 0; $i < Login::MAX_FAILURES_PER_EMAIL - 1; $i++) {
            $this->attemptLogin('crew@example.com', 'falsch');
        }

        $this->attemptLogin('crew@example.com', 'Richtig1Passwort')->assertHasNoErrors();
        auth()->logout();

        $this->attemptLogin('crew@example.com', 'falsch');
        $this->attemptLogin('crew@example.com', 'Richtig1Passwort')->assertHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_email_lock_is_case_insensitive(): void
    {
        $this->cockpitUser();

        for ($i = 0; $i < Login::MAX_FAILURES_PER_EMAIL; $i++) {
            $this->attemptLogin('CREW@example.com', 'falsch');
        }

        $this->attemptLogin('crew@example.com', 'Richtig1Passwort')->assertHasErrors(['data.email']);
        $this->assertGuest();
    }

    public function test_ip_is_locked_after_too_many_failures_across_accounts(): void
    {
        $this->cockpitUser();

        for ($i = 0; $i < Login::MAX_FAILURES_PER_IP; $i++) {
            $this->attemptLogin("versuch{$i}@example.com", 'falsch');
        }

        $this->attemptLogin('crew@example.com', 'Richtig1Passwort')->assertHasErrors(['data.email']);
        $this->assertGuest();
    }
}
