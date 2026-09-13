<?php

namespace Tests\Feature\Reports;

use App\Filament\Pages\CompanySettingsPage;
use App\Models\CompanySetting;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanySettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShieldSeeder::class);
    }

    public function test_crew_member_cannot_access_the_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Z-Lab-Crew');

        $this->actingAs($user)->get('/company-settings-page')->assertForbidden();
    }

    public function test_boss_can_view_and_save_company_settings(): void
    {
        $boss = User::factory()->create();
        $boss->assignRole('Z-Lab-Boss');

        $this->actingAs($boss)->get('/company-settings-page')->assertOk();

        Livewire::test(CompanySettingsPage::class)
            ->fillForm([
                'name' => 'Z-Lab GmbH',
                'street' => 'Firmenweg 5',
                'zip' => '54321',
                'city' => 'Firmenstadt',
            ])
            ->call('save')
            ->assertNotified();

        $this->assertSame('Z-Lab GmbH', CompanySetting::current()->fresh()->name);
        $this->assertSame('Firmenweg 5', CompanySetting::current()->fresh()->street);
    }
}
