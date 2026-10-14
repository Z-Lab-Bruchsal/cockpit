<?php

namespace Tests\Feature\Reports;

use App\Filament\Pages\CompanySettingsPage;
use App\Models\CompanySetting;
use App\Models\Section;
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

        $this->actingAs($user)->get('/cockpit/company-settings-page')->assertForbidden();
    }

    public function test_boss_can_view_and_save_company_settings(): void
    {
        $boss = User::factory()->create();
        $boss->assignRole('Z-Lab-Boss');

        $this->actingAs($boss)->get('/cockpit/company-settings-page')->assertOk();

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

    public function test_migration_prefills_z_lab_defaults(): void
    {
        $settings = CompanySetting::current();

        $this->assertSame('Z-Lab', $settings->name);
        $this->assertSame('Bruchsal', $settings->city);
        $this->assertSame('Z-Lab Cockpit', $settings->appTitle());
        $this->assertSame('Z-Lab Bruchsal', $settings->homepageTitle());
    }

    public function test_app_title_is_used_as_application_and_mail_sender_name(): void
    {
        CompanySetting::current()->update(['app_title' => 'Z-Lab Bruchsal Verwaltung']);

        CompanySetting::applyToConfig();

        $this->assertSame('Z-Lab Bruchsal Verwaltung', config('app.name'));
        $this->assertSame('Z-Lab Bruchsal Verwaltung', config('mail.from.name'));

        $this->actingAs(User::factory()->create());

        $this->withoutVite()->get('/cockpit')->assertSee('Z-Lab Bruchsal Verwaltung');
    }

    public function test_app_title_is_required(): void
    {
        $boss = User::factory()->create();
        $boss->assignRole('Z-Lab-Boss');
        $this->actingAs($boss);

        Livewire::test(CompanySettingsPage::class)
            ->fillForm(['app_title' => ''])
            ->call('save')
            ->assertHasFormErrors(['app_title' => 'required']);
    }

    public function test_cockpit_colors_are_taken_from_settings(): void
    {
        CompanySetting::current()->update([
            'cockpit_primary_color' => '#2563eb',
            'cockpit_danger_color' => '#dc2626',
            'cockpit_success_color' => null,
        ]);

        $this->assertSame(
            ['primary' => '#2563eb', 'danger' => '#dc2626'],
            CompanySetting::cockpitColors(),
        );
    }

    public function test_unsafe_color_values_are_ignored(): void
    {
        CompanySetting::current()->update([
            'cockpit_primary_color' => 'red;background:url(//evil)',
            'primary_color' => 'red;background:url(//evil)',
        ]);

        $this->assertArrayNotHasKey('primary', CompanySetting::cockpitColors());
        $this->assertSame('#f59e0b', CompanySetting::current()->homepageCssVariables()['--brand']);
    }

    public function test_boss_can_pick_colors_and_invalid_values_are_rejected(): void
    {
        $boss = User::factory()->create();
        $boss->assignRole('Z-Lab-Boss');
        $this->actingAs($boss);

        Livewire::test(CompanySettingsPage::class)
            ->fillForm(['primary_color' => 'blau'])
            ->call('save')
            ->assertHasFormErrors(['primary_color' => 'regex']);

        Livewire::test(CompanySettingsPage::class)
            ->fillForm([
                'primary_color' => '#1d4ed8',
                'secondary_color' => '#0f172a',
                'cockpit_primary_color' => '#16a34a',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $settings = CompanySetting::current();
        $this->assertSame('#1d4ed8', $settings->primary_color);
        $this->assertSame('#16a34a', $settings->cockpit_primary_color);
    }

    public function test_text_color_on_colored_backgrounds_stays_readable(): void
    {
        $this->assertSame('#111827', CompanySetting::contrastTextColor('#fff7ed'));
        $this->assertSame('#111827', CompanySetting::contrastTextColor('#f59e0b'));
        $this->assertSame('#ffffff', CompanySetting::contrastTextColor('#1d4ed8'));
        $this->assertSame('#ffffff', CompanySetting::contrastTextColor('#000'));
    }

    public function test_homepage_uses_configured_colors(): void
    {
        CompanySetting::current()->update(['primary_color' => '#1d4ed8', 'secondary_color' => '#0f172a']);

        $this->withoutVite()->get('/')
            ->assertSee('--brand: #1d4ed8; --brand-contrast: #ffffff; --accent: #0f172a; --accent-contrast: #ffffff', false);
    }

    public function test_boss_can_configure_footer(): void
    {
        $boss = User::factory()->create();
        $boss->assignRole('Z-Lab-Boss');
        $this->actingAs($boss);

        $impressum = Section::factory()->standalone()->create();

        Livewire::test(CompanySettingsPage::class)
            ->fillForm([
                'footer_text' => 'Z-LAB',
                'footer_links' => [
                    ['label' => 'Impressum', 'section_id' => $impressum->id, 'url' => null],
                ],
                'social_links' => [
                    ['platform' => 'youtube', 'url' => 'https://www.youtube.com/@z-lab'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = CompanySetting::current();
        $this->assertSame('Impressum', $settings->footer_links[0]['label']);
        $this->assertSame('youtube', $settings->social_links[0]['platform']);
    }

    public function test_footer_link_needs_a_section_or_url(): void
    {
        $boss = User::factory()->create();
        $boss->assignRole('Z-Lab-Boss');
        $this->actingAs($boss);

        Livewire::test(CompanySettingsPage::class)
            ->fillForm([
                'footer_links' => [
                    ['label' => 'Leer', 'section_id' => null, 'url' => null],
                ],
            ])
            ->call('save')
            ->assertHasErrors();
    }
}
