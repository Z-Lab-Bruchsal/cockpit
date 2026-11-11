<?php

namespace Tests\Feature\Analytics;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WeeklyHoursFieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ShieldSeeder::class);
    }

    public function test_boss_can_store_agreed_weekly_hours(): void
    {
        $boss = User::factory()->create();
        $boss->assignRole('Z-Lab-Boss');
        $this->actingAs($boss);
        $leader = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $leader->id])
            ->assertFormFieldVisible('weekly_hours')
            ->fillForm(['weekly_hours' => '7.5'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('7.50', $leader->fresh()->weekly_hours);
    }

    public function test_weekly_hours_must_be_a_sensible_number(): void
    {
        $boss = User::factory()->create();
        $boss->assignRole('Z-Lab-Boss');
        $this->actingAs($boss);

        Livewire::test(EditUser::class, ['record' => User::factory()->create()->id])
            ->fillForm(['weekly_hours' => '-1'])
            ->call('save')
            ->assertHasFormErrors(['weekly_hours' => 'min']);
    }

    public function test_field_is_hidden_without_supervisor_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['ViewAny:User', 'View:User', 'Update:User']);
        $this->actingAs($user);

        Livewire::test(EditUser::class, ['record' => User::factory()->create()->id])
            ->assertFormFieldHidden('weekly_hours');
    }
}
