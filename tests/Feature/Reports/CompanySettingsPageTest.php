<?php

namespace Tests\Feature\Reports;

use App\Filament\Pages\CompanySettingsPage;
use App\Models\CompanySetting;
use App\Models\CourseTemplate;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

        $user = User::factory()->create();
        $user->assignRole('Z-Lab-Crew');
        $this->actingAs($user);

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

    public function test_brand_text_color_is_darkened_until_readable_on_white(): void
    {
        $this->assertSame('#1d4ed8', CompanySetting::readableOnWhite('#1d4ed8'));
        $this->assertSame('rgb(0 0 0)', CompanySetting::readableOnWhite('rgb(0 0 0)'));

        foreach (['#ffd600', '#e41f6c', '#fff', '#ffffff80'] as $color) {
            $readable = CompanySetting::readableOnWhite($color);

            $this->assertNotSame($color, $readable);
            $this->assertGreaterThanOrEqual(4.5, 1.05 / (CompanySetting::relativeLuminance($readable) + 0.05), $color);
        }

        $this->assertSame('#ffd600', CompanySetting::readableOnWhite('#ffd600', 1.0));
        $this->assertGreaterThanOrEqual(3.0, 1.05 / (CompanySetting::relativeLuminance(CompanySetting::readableOnWhite('#ffd600', 3.0)) + 0.05));
    }

    public function test_brand_variables_match_the_z_lab_pink(): void
    {
        $this->assertSame([
            '--brand' => '#e41f6c',
            '--brand-contrast' => '#ffffff',
            '--brand-heading' => '#e41f6c',
            '--brand-ink' => '#c41b5d',
        ], CompanySetting::brandCssVariables('#e41f6c'));

        $this->assertSame('#c41b5d', CompanySetting::darken('#e41f6c', 0.86));
        $this->assertSame('red', CompanySetting::darken('red', 0.5));
    }

    public function test_migrations_set_the_z_lab_pink_as_homepage_color(): void
    {
        $this->assertSame('#e41f6c', CompanySetting::current()->primary_color);
        $this->assertSame('#e41f6c', CompanySetting::current()->homepageCssVariables()['--brand']);
    }

    public function test_migrations_set_the_z_lab_logo_and_ribbon_links(): void
    {
        $settings = CompanySetting::current();

        $this->assertSame('company/z-lab-logo.svg', $settings->logo_path);
        $this->assertSame('Logo Z-LAB Zukunftslabor', $settings->logo_alt);
        $this->assertTrue(Storage::disk('public')->exists('company/z-lab-logo.svg'));
        $this->assertSame('https://www.auerbach-stiftung.de/newsletter-12-de', $settings->newsletter_url);
        $this->assertSame('https://www.auerbach-stiftung.de/spenden-11-de', $settings->donation_url);
    }

    public function test_homepage_shows_logo_and_ribbons(): void
    {
        $this->withoutVite()->get('/')
            ->assertSee('company/z-lab-logo.svg', false)
            ->assertSee('alt="Logo Z-LAB Zukunftslabor"', false)
            ->assertSee('href="https://www.auerbach-stiftung.de/newsletter-12-de"', false)
            ->assertSee('href="https://www.auerbach-stiftung.de/spenden-11-de"', false)
            ->assertSee('Jetzt spenden');
    }

    public function test_header_ribbons_skip_empty_and_unsafe_links(): void
    {
        CompanySetting::current()->update(['newsletter_url' => null, 'donation_url' => 'javascript:alert(1)']);

        $this->assertSame([], CompanySetting::current()->headerRibbons());

        $this->withoutVite()->get('/')
            ->assertDontSee('javascript:alert(1)', false)
            ->assertDontSee('Jetzt spenden')
            ->assertDontSee('Zum Newsletter');
    }

    public function test_boss_can_set_ribbon_links_and_invalid_urls_are_rejected(): void
    {
        $boss = User::factory()->create();
        $boss->assignRole('Z-Lab-Boss');
        $this->actingAs($boss);

        Livewire::test(CompanySettingsPage::class)
            ->fillForm(['donation_url' => 'kein link'])
            ->call('save')
            ->assertHasFormErrors(['donation_url' => 'url']);

        Livewire::test(CompanySettingsPage::class)
            ->fillForm(['newsletter_url' => 'https://example.test/newsletter', 'donation_url' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([
            ['label' => 'Zum Newsletter', 'url' => 'https://example.test/newsletter', 'isDonation' => false],
        ], CompanySetting::current()->headerRibbons());
    }

    public function test_homepage_uses_configured_colors(): void
    {
        CompanySetting::current()->update(['primary_color' => '#1d4ed8', 'secondary_color' => '#0f172a']);

        $this->withoutVite()->get('/')
            ->assertSee('--brand: #1d4ed8; --brand-contrast: #ffffff; --brand-heading: #1d4ed8; --brand-ink: #1943ba; --accent: #0f172a; --accent-contrast: #ffffff', false);
    }

    public function test_course_template_accent_color_overrides_all_brand_variables(): void
    {
        $template = new CourseTemplate(['accent_color' => '#e41f6c']);

        $this->assertSame('--brand: #e41f6c; --brand-contrast: #ffffff; --brand-heading: #e41f6c; --brand-ink: #c41b5d', $template->accentStyle());
        $this->assertSame('', (new CourseTemplate)->accentStyle());
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
