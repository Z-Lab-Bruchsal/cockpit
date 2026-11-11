<?php

namespace Tests\Feature\Analytics;

use App\Filament\Pages\AnalyticsPage;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ShieldSeeder::class);
        $this->travelTo(Carbon::parse('2026-11-11 12:00', 'Europe/Berlin'));
    }

    protected function actingAsRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_boss_sees_the_analytics_page(): void
    {
        $this->actingAsRole('Z-Lab-Boss');

        $this->get(AnalyticsPage::getUrl())
            ->assertOk()
            ->assertSee('Auswertungen')
            ->assertSee('Drucken');
    }

    public function test_crew_may_not_open_the_analytics_page(): void
    {
        $this->actingAsRole('Z-Lab-Crew');

        $this->get(AnalyticsPage::getUrl())->assertForbidden();
    }

    public function test_filters_default_to_the_current_year(): void
    {
        $this->actingAsRole('Z-Lab-Boss');

        Livewire::test(AnalyticsPage::class)
            ->assertSet('filters.from', fn (?string $from): bool => str_starts_with((string) $from, '2026-01-01'))
            ->assertSet('filters.to', fn (?string $to): bool => str_starts_with((string) $to, '2026-12-31'));
    }

    public function test_pdf_is_downloaded_with_the_selected_sections(): void
    {
        $this->actingAsRole('Z-Lab-Boss');

        Livewire::test(AnalyticsPage::class)
            ->set('filters.from', '2026-03-01')
            ->set('filters.to', '2026-03-31')
            ->callAction('pdf', data: ['sections' => ['courses', 'top_kids', 'orders', 'work_hours']])
            ->assertHasNoActionErrors()
            ->assertFileDownloaded('auswertung-2026-03-01-bis-2026-03-31.pdf');
    }

    public function test_pdf_contains_every_section(): void
    {
        $this->actingAsRole('Z-Lab-Boss');

        Livewire::test(AnalyticsPage::class)
            ->callAction('pdf', data: ['sections' => ['courses', 'participants', 'ages', 'months', 'interests', 'top_courses', 'top_kids', 'orders', 'work_hours']])
            ->assertHasNoActionErrors()
            ->assertFileDownloaded('auswertung-2026-01-01-bis-2026-12-31.pdf');
    }
}
