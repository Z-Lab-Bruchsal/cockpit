<?php

namespace Tests\Feature\TimeClock;

use AlexanderGabriel\FilamentDashboardTabs\Models\DashboardTab;
use AlexanderGabriel\FilamentDashboardTabs\Models\DashboardTabWidget;
use App\Enums\TimeClockState;
use App\Filament\Resources\Bookings\Widgets\TimeClockWidget;
use App\Filament\Resources\Todos\Widgets\MyTodosWidget;
use App\Models\User;
use App\Services\WorkTime\TimeClockService;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TimeClockWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ShieldSeeder::class);
    }

    protected function signIn(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Z-Lab-Crew');

        $this->actingAs($user);

        return $user;
    }

    public function test_compact_time_clock_renders_without_section(): void
    {
        $this->signIn();

        Livewire::test(TimeClockWidget::class, ['compact' => true])
            ->assertSee('Heute 0h 0min')
            ->assertDontSee('Zeiterfassung')
            ->assertActionVisible('clockIn')
            ->assertActionHidden('clockOut');
    }

    public function test_full_time_clock_still_renders_section(): void
    {
        $this->signIn();

        Livewire::test(TimeClockWidget::class)
            ->assertSee('Zeiterfassung')
            ->assertSee('Nicht eingestempelt');
    }

    public function test_clocking_in_from_compact_time_clock_notifies_other_time_clocks(): void
    {
        $user = $this->signIn();

        Livewire::test(TimeClockWidget::class, ['compact' => true])
            ->callAction('clockIn')
            ->assertDispatched('time-clock-updated')
            ->assertActionHidden('clockIn')
            ->assertActionVisible('clockOut');

        $this->assertSame(TimeClockState::Working, app(TimeClockService::class)->currentState($user));
    }

    public function test_time_clock_refreshes_when_another_one_booked(): void
    {
        $user = $this->signIn();

        $component = Livewire::test(TimeClockWidget::class)->assertSee('Nicht eingestempelt');

        app(TimeClockService::class)->clockIn($user, $user);

        $component->dispatch('time-clock-updated')->assertSee('Am Arbeiten');
    }

    public function test_dashboard_header_bar_contains_time_clock_even_if_tab_has_no_time_clock_widget(): void
    {
        $user = $this->signIn();
        $tab = DashboardTab::factory()->for($user)->create();
        DashboardTabWidget::factory()->for($tab, 'tab')->create(['widget' => MyTodosWidget::class]);

        $this->get('/cockpit')
            ->assertOk()
            ->assertSee('fi-dashboard-tabs-bar', false)
            ->assertSeeLivewire(TimeClockWidget::class)
            ->assertSee('Kommen');
    }
}
