<?php

namespace Tests\Feature\Settings;

use App\Filament\Clusters\Settings\Pages\CockpitPage;
use App\Filament\Clusters\Settings\Pages\CompanyPage;
use App\Filament\Clusters\Settings\Pages\CompanySettingsSubPage;
use App\Filament\Clusters\Settings\Pages\CourseBookingPage;
use App\Filament\Clusters\Settings\Pages\FooterPage;
use App\Filament\Clusters\Settings\Pages\HomepageSettingsPage;
use App\Filament\Clusters\Settings\Pages\OrderReportSettingsPage;
use App\Filament\Clusters\Settings\Pages\SchoolClassesPage;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Filament\Pages\DesignPage;
use App\Filament\Resources\Users\UserResource;
use App\Models\CompanySetting;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SettingsClusterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<class-string<CompanySettingsSubPage>>
     */
    protected const SETTINGS_PAGES = [
        CompanyPage::class,
        CockpitPage::class,
        HomepageSettingsPage::class,
        FooterPage::class,
        OrderReportSettingsPage::class,
        CourseBookingPage::class,
        SchoolClassesPage::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShieldSeeder::class);
    }

    protected function boss(): User
    {
        $boss = User::factory()->create();
        $boss->assignRole('Z-Lab-Boss');

        return $boss;
    }

    public function test_boss_can_open_every_settings_page(): void
    {
        $this->actingAs($this->boss());

        foreach (self::SETTINGS_PAGES as $page) {
            $this->get($page::getUrl())->assertOk();
        }

        $this->get(DesignPage::getUrl())->assertOk();
        $this->get(UserResource::getUrl())->assertOk();
        $this->get(SettingsCluster::getUrl())->assertRedirect();
    }

    public function test_settings_pages_live_below_the_settings_cluster(): void
    {
        $this->assertStringEndsWith('/cockpit/einstellungen/firma', CompanyPage::getUrl());
        $this->assertStringEndsWith('/cockpit/einstellungen/design', DesignPage::getUrl());
        $this->assertStringEndsWith('/cockpit/einstellungen/users', UserResource::getUrl());
    }

    public function test_settings_pages_require_the_settings_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Z-Lab-Crew');
        $this->actingAs($user);

        foreach (self::SETTINGS_PAGES as $page) {
            $this->get($page::getUrl())->assertForbidden();
        }
    }

    public function test_design_permission_alone_opens_only_the_design_page(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('Homepage:Design'));
        $this->actingAs($user);

        $this->get(DesignPage::getUrl())->assertOk();
        $this->get(CompanyPage::getUrl())->assertForbidden();
        $this->assertTrue(SettingsCluster::canAccessClusteredComponents());
    }

    public function test_cluster_is_hidden_without_any_accessible_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertFalse(SettingsCluster::canAccessClusteredComponents());
    }

    public function test_saving_one_page_keeps_the_fields_of_the_other_pages(): void
    {
        $this->actingAs($this->boss());
        CompanySetting::current()->update(['name' => 'Z-Lab GmbH', 'footer_text' => 'Z-LAB']);

        Livewire::test(CourseBookingPage::class)
            ->fillForm(['hold_minutes' => 30])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNoRedirect();

        $settings = CompanySetting::current();
        $this->assertSame(30, (int) $settings->hold_minutes);
        $this->assertSame('Z-Lab GmbH', $settings->name);
        $this->assertSame('Z-LAB', $settings->footer_text);
    }

    public function test_cockpit_page_reloads_only_when_colors_change(): void
    {
        $this->actingAs($this->boss());

        Livewire::test(CockpitPage::class)
            ->fillForm(['app_title' => 'Neuer Titel'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNoRedirect();

        Livewire::test(CockpitPage::class)
            ->fillForm(['cockpit_primary_color' => '#16a34a'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(CockpitPage::getUrl());

        $this->assertSame('Neuer Titel', CompanySetting::current()->app_title);
    }

    public function test_old_settings_url_redirects_to_the_new_page(): void
    {
        $this->get('/cockpit/company-settings-page')->assertRedirect('/cockpit/einstellungen/firma');
    }
}
