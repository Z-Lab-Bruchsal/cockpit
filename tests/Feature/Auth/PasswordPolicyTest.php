<?php

namespace Tests\Feature\Auth;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function passes(string $password): bool
    {
        return Validator::make(['password' => $password], ['password' => Password::default()])->passes();
    }

    public function test_weak_passwords_are_rejected(): void
    {
        $this->assertFalse($this->passes('Kurz1'));
        $this->assertFalse($this->passes('alleskleinmit1zahl'));
        $this->assertFalse($this->passes('ALLESGROSSMIT1ZAHL'));
        $this->assertFalse($this->passes('OhneJedeZahlHier'));
    }

    public function test_strong_password_is_accepted(): void
    {
        $this->assertTrue($this->passes('Sicheres1Passwort'));
    }

    public function test_creating_a_user_with_a_weak_password_fails(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('Create:User'), Permission::findOrCreate('ViewAny:User'));
        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Neue Person',
                'email' => 'neu@example.com',
                'password' => 'schwach',
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);

        $this->assertDatabaseMissing(User::class, ['email' => 'neu@example.com']);
    }

    public function test_creating_a_user_with_a_strong_password_succeeds(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('Create:User'), Permission::findOrCreate('ViewAny:User'));
        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Neue Person',
                'email' => 'neu@example.com',
                'password' => 'Sicheres1Passwort',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(User::class, ['email' => 'neu@example.com']);
    }
}
