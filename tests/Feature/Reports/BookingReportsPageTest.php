<?php

namespace Tests\Feature\Reports;

use App\Filament\Pages\BookingReportsPage;
use App\Models\Booking;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingReportsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShieldSeeder::class);
    }

    public function test_crew_member_can_access_the_page_but_not_the_supervisor_section(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Z-Lab-Crew');

        $this->actingAs($user)->get('/booking-reports-page')->assertOk();

        Livewire::test(BookingReportsPage::class)
            ->assertActionHidden('generateSupervisorMonthlyReport')
            ->assertActionHidden('generateSupervisorYearlyReport');
    }

    public function test_boss_can_access_the_supervisor_section(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Z-Lab-Boss');

        $this->actingAs($user);

        Livewire::test(BookingReportsPage::class)
            ->assertActionVisible('generateSupervisorMonthlyReport')
            ->assertActionVisible('generateSupervisorYearlyReport');
    }

    public function test_user_without_any_role_cannot_access_the_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/booking-reports-page')->assertForbidden();
    }

    public function test_crew_member_can_download_their_own_monthly_report(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Z-Lab-Crew');

        Booking::factory()->create([
            'user_id' => $user->id,
            'start_at' => '2026-03-05 08:00:00',
            'end_at' => '2026-03-05 16:00:00',
        ]);

        $this->actingAs($user);

        Livewire::test(BookingReportsPage::class)
            ->fillForm(['user_id' => $user->id, 'year' => 2026, 'month' => 3])
            ->callAction('generateMonthlyReport')
            ->assertFileDownloaded();
    }

    public function test_crew_member_cannot_generate_a_report_for_another_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Z-Lab-Crew');
        $otherUser = User::factory()->create();

        Booking::factory()->create([
            'user_id' => $user->id,
            'start_at' => '2026-03-05 08:00:00',
            'end_at' => '2026-03-05 09:00:00',
        ]);
        Booking::factory()->create([
            'user_id' => $otherUser->id,
            'start_at' => '2026-03-06 08:00:00',
            'end_at' => '2026-03-06 12:00:00',
        ]);

        $this->actingAs($user);

        // Even if the form state is tampered with to reference another user,
        // the user_id field's options are restricted to the acting user's own
        // id (Crew lacks Worktimes:ViewForeign), so Filament's own select
        // validation rejects the out-of-options value and no report for the
        // other user is ever generated.
        Livewire::test(BookingReportsPage::class)
            ->fillForm(['user_id' => $otherUser->id, 'year' => 2026, 'month' => 3])
            ->callAction('generateMonthlyReport')
            ->assertNoFileDownloaded();
    }

    public function test_boss_can_download_supervisor_reports(): void
    {
        $boss = User::factory()->create();
        $boss->assignRole('Z-Lab-Boss');

        $this->actingAs($boss);

        Livewire::test(BookingReportsPage::class)
            ->fillForm(['supervisor_year' => 2026, 'supervisor_month' => 3])
            ->callAction('generateSupervisorMonthlyReport')
            ->assertFileDownloaded();

        Livewire::test(BookingReportsPage::class)
            ->fillForm(['supervisor_year' => 2026])
            ->callAction('generateSupervisorYearlyReport')
            ->assertFileDownloaded();
    }
}
