<?php

namespace Tests\Feature\DashboardTabs;

use AlexanderGabriel\FilamentDashboardTabs\Models\DashboardTab;
use AlexanderGabriel\FilamentDashboardTabs\Models\DashboardTabWidget;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Todos\Widgets\MyTodosWidget;
use App\Filament\Widgets\Analytics\TopKidsWidget;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\Enrollment;
use App\Models\Kid;
use App\Models\Registration;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTabFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ShieldSeeder::class);
        $this->travelTo(Carbon::parse('2026-11-11 12:00', 'Europe/Berlin'));

        $this->user = User::factory()->create();
        $this->user->assignRole('Z-Lab-Boss');
        $this->actingAs($this->user);
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @param  list<class-string>  $widgets
     */
    protected function tab(string $name, array $widgets, ?array $filters = null, int $sort = 0): DashboardTab
    {
        $tab = DashboardTab::factory()->for($this->user)->create(['name' => $name, 'sort' => $sort, 'filters' => $filters]);

        foreach ($widgets as $index => $widget) {
            DashboardTabWidget::factory()->for($tab, 'tab')->create(['widget' => $widget, 'sort' => $index]);
        }

        return $tab;
    }

    public function test_dashboard_route_uses_the_app_dashboard(): void
    {
        $this->get('/cockpit')->assertOk()->assertSeeLivewire(Dashboard::class);
    }

    public function test_filter_form_is_only_shown_on_tabs_with_filtered_widgets(): void
    {
        $plain = $this->tab('Alltag', [MyTodosWidget::class]);
        $analytics = $this->tab('Zahlen', [TopKidsWidget::class], sort: 1);

        $page = Livewire::test(Dashboard::class, ['activeTab' => $plain->id]);
        $this->assertFalse($page->instance()->showsFiltersForm());

        $page->set('activeTab', $analytics->id);
        $this->assertTrue($page->instance()->showsFiltersForm());
    }

    public function test_new_tab_starts_with_default_filters(): void
    {
        $this->tab('Zahlen', [TopKidsWidget::class]);

        Livewire::test(Dashboard::class)
            ->assertSet('filters.from', fn (?string $from): bool => str_starts_with((string) $from, '2026-01-01'))
            ->assertSet('filters.to', fn (?string $to): bool => str_starts_with((string) $to, '2026-12-31'));
    }

    public function test_changed_filters_are_stored_on_the_current_tab(): void
    {
        $tab = $this->tab('Zahlen', [TopKidsWidget::class]);
        $other = $this->tab('Andere', [TopKidsWidget::class], sort: 1);

        Livewire::test(Dashboard::class, ['activeTab' => $tab->id])
            ->set('filters.from', '2026-03-01')
            ->set('filters.age_min', '8');

        $this->assertSame('2026-03-01', $tab->fresh()->filters['from']);
        $this->assertSame('8', $tab->fresh()->filters['age_min']);
        $this->assertNull($other->fresh()->filters);
    }

    public function test_switching_tabs_loads_their_own_filters(): void
    {
        $first = $this->tab('Frühjahr', [TopKidsWidget::class], ['from' => '2026-03-01', 'to' => '2026-05-31']);
        $second = $this->tab('Herbst', [TopKidsWidget::class], ['from' => '2026-09-01', 'to' => '2026-11-30'], sort: 1);

        Livewire::test(Dashboard::class, ['activeTab' => $first->id])
            ->assertSet('filters.from', fn (?string $from): bool => str_starts_with((string) $from, '2026-03-01'))
            ->set('activeTab', $second->id)
            ->assertSet('filters.from', fn (?string $from): bool => str_starts_with((string) $from, '2026-09-01'))
            ->set('activeTab', $first->id)
            ->assertSet('filters.to', fn (?string $to): bool => str_starts_with((string) $to, '2026-05-31'));
    }

    public function test_widgets_receive_the_filters_of_their_tab(): void
    {
        $instance = CourseInstance::factory()->for(Course::factory()->create(['name' => 'Löten']))
            ->withDates(1, Carbon::parse('2026-03-01 16:00'))
            ->create();
        Enrollment::factory()->create([
            'registration_id' => Registration::factory()->create(['course_instance_id' => $instance->id])->id,
            'kid_id' => Kid::factory()->create(['name' => 'Mia'])->id,
            'kid_name' => 'Mia',
        ]);

        $tab = $this->tab('Zahlen', [TopKidsWidget::class], ['from' => '2026-01-01', 'to' => '2026-12-31']);

        $page = Livewire::test(Dashboard::class, ['activeTab' => $tab->id]);

        $this->assertStringStartsWith('2026-01-01', $page->instance()->widgetProperties(TopKidsWidget::class)['pageFilters']['from']);
        $this->assertArrayNotHasKey('pageFilters', $page->instance()->widgetProperties(MyTodosWidget::class));
    }

    public function test_reset_restores_the_defaults_and_stores_them(): void
    {
        $tab = $this->tab('Zahlen', [TopKidsWidget::class], ['from' => '2025-03-01', 'to' => '2025-05-31', 'age_min' => '8']);

        Livewire::test(Dashboard::class, ['activeTab' => $tab->id])
            ->callAction('resetFilters')
            ->assertSet('filters.age_min', null)
            ->assertSet('filters.from', fn (?string $from): bool => str_starts_with((string) $from, '2026-01-01'));

        $this->assertNull($tab->fresh()->filters['age_min']);
        $this->assertStringStartsWith('2026-01-01', $tab->fresh()->filters['from']);
    }

    public function test_print_description_names_the_active_filters(): void
    {
        $tab = $this->tab('Zahlen', [TopKidsWidget::class], ['from' => '2026-03-01', 'to' => '2026-05-31', 'age_min' => '8']);

        $page = Livewire::test(Dashboard::class, ['activeTab' => $tab->id]);

        $this->assertSame('Zeitraum: 01.03.2026 – 31.05.2026 · Alter: 8 – … Jahre', $page->instance()->getFiltersDescription());
    }
}
