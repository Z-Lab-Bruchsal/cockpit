<?php

namespace Tests\Feature\Analytics;

use App\Filament\Widgets\Analytics\CourseStatsWidget;
use App\Filament\Widgets\Analytics\EnrollmentsPerMonthChartWidget;
use App\Filament\Widgets\Analytics\InterestChartWidget;
use App\Filament\Widgets\Analytics\KidAgeChartWidget;
use App\Filament\Widgets\Analytics\OrderCostsByMerchantChartWidget;
use App\Filament\Widgets\Analytics\OrderCostsPerMonthChartWidget;
use App\Filament\Widgets\Analytics\OrderCostStatsWidget;
use App\Filament\Widgets\Analytics\ParticipantStatsWidget;
use App\Filament\Widgets\Analytics\TopCoursesWidget;
use App\Filament\Widgets\Analytics\TopKidsWidget;
use App\Filament\Widgets\Analytics\WorkHoursPerWeekChartWidget;
use App\Filament\Widgets\Analytics\WorkHoursWidget;
use App\Models\Booking;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\Enrollment;
use App\Models\Kid;
use App\Models\Registration;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Filament\Widgets\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AnalyticsWidgetsTest extends TestCase
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

    /**
     * @return array<string, array{class-string<Widget>}>
     */
    public static function widgets(): array
    {
        return collect([
            CourseStatsWidget::class,
            ParticipantStatsWidget::class,
            KidAgeChartWidget::class,
            EnrollmentsPerMonthChartWidget::class,
            InterestChartWidget::class,
            TopKidsWidget::class,
            TopCoursesWidget::class,
            OrderCostStatsWidget::class,
            OrderCostsPerMonthChartWidget::class,
            OrderCostsByMerchantChartWidget::class,
            WorkHoursWidget::class,
            WorkHoursPerWeekChartWidget::class,
        ])->mapWithKeys(fn (string $widget): array => [class_basename($widget) => [$widget]])->all();
    }

    /**
     * @param  class-string<Widget>  $widget
     */
    #[DataProvider('widgets')]
    public function test_widget_renders_with_page_filters_for_the_boss(string $widget): void
    {
        $this->actingAsRole('Z-Lab-Boss');

        $this->assertTrue($widget::canView());

        Livewire::test($widget, ['pageFilters' => ['from' => '2026-01-01', 'to' => '2026-12-31']])->assertOk();
    }

    /**
     * @param  class-string<Widget>  $widget
     */
    #[DataProvider('widgets')]
    public function test_widget_is_hidden_without_permission(string $widget): void
    {
        $this->actingAsRole('Z-Lab-Crew');

        $this->assertFalse($widget::canView());
    }

    public function test_top_kids_widget_lists_kids_of_the_filtered_period(): void
    {
        $this->actingAsRole('Z-Lab-Boss');
        $course = Course::factory()->create(['name' => 'Löten']);
        $instance = CourseInstance::factory()->for($course)->withDates(1, Carbon::parse('2026-03-01 16:00'))->create();
        $kid = Kid::factory()->create(['name' => 'Mia']);

        Enrollment::factory()->create([
            'registration_id' => Registration::factory()->create(['course_instance_id' => $instance->id])->id,
            'kid_id' => $kid->id,
            'kid_name' => 'Mia',
        ]);

        Livewire::test(TopKidsWidget::class, ['pageFilters' => ['from' => '2026-01-01', 'to' => '2026-12-31']])
            ->assertSee('Mia')
            ->assertSee('Löten');

        Livewire::test(TopKidsWidget::class, ['pageFilters' => ['from' => '2026-06-01', 'to' => '2026-12-31']])
            ->assertDontSee('Mia');
    }

    public function test_work_hours_widget_shows_staff_above_agreed_hours(): void
    {
        $this->actingAsRole('Z-Lab-Boss');
        $leader = User::factory()->create(['name' => 'Lea Leitung', 'weekly_hours' => 5]);

        Booking::factory()->create([
            'user_id' => $leader->id,
            'start_at' => Carbon::parse('2026-11-10 09:00'),
            'end_at' => Carbon::parse('2026-11-10 16:00'),
            'worked_minutes' => 420,
        ]);

        Livewire::test(WorkHoursWidget::class, ['pageFilters' => ['from' => '2026-11-01', 'to' => '2026-11-30']])
            ->assertSee('Lea Leitung')
            ->assertSee('7h 00min')
            ->assertSee('+ 2h 00min');
    }

    public function test_cost_widgets_require_order_access(): void
    {
        $user = $this->actingAsRole('Jugendleiter');
        $user->givePermissionTo('View:OrderCostStatsWidget');

        $this->assertFalse(OrderCostStatsWidget::canView());
    }
}
