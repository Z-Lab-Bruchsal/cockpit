<?php

namespace Tests\Feature\DashboardTabs;

use AlexanderGabriel\FilamentDashboardTabs\Enums\WidgetWidth;
use AlexanderGabriel\FilamentDashboardTabs\Models\DashboardTab;
use AlexanderGabriel\FilamentDashboardTabs\Models\DashboardTabWidget;
use AlexanderGabriel\FilamentDashboardTabs\Pages\Dashboard;
use App\Filament\Resources\Bookings\Widgets\TimeClockWidget;
use App\Filament\Resources\Orders\Widgets\MyOrdersWidget;
use App\Filament\Resources\Todos\Widgets\MyTodosWidget;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ShieldSeeder::class);
    }

    protected function userWithRole(string $role = 'Z-Lab-Crew'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user);

        return $user;
    }

    protected function editingDashboard(): Testable
    {
        return Livewire::test(Dashboard::class)->callAction('toggleEditing')->assertSet('isEditing', true);
    }

    public function test_first_visit_creates_default_tab_with_permitted_widgets(): void
    {
        $user = $this->userWithRole();

        $this->get('/cockpit')->assertOk();

        $tab = $user->dashboardTabs()->sole();

        $this->assertSame('Übersicht', $tab->name);
        $this->assertSame(
            [TimeClockWidget::class, MyTodosWidget::class, MyOrdersWidget::class],
            $tab->widgets->pluck('widget')->all(),
        );
        $this->assertSame(
            [WidgetWidth::Half, WidgetWidth::Full, WidgetWidth::Full],
            $tab->widgets->pluck('width')->all(),
        );
    }

    public function test_default_tab_only_contains_widgets_the_user_may_view(): void
    {
        $user = $this->userWithRole('Jugendleiter');

        Livewire::test(Dashboard::class);

        $this->assertSame([TimeClockWidget::class], $user->dashboardTabs()->sole()->widgets->pluck('widget')->all());
    }

    public function test_default_tab_is_created_only_once(): void
    {
        $user = $this->userWithRole();

        Livewire::test(Dashboard::class);
        Livewire::test(Dashboard::class);

        $this->assertSame(1, $user->dashboardTabs()->count());
    }

    public function test_widgets_without_permission_are_not_rendered(): void
    {
        $user = $this->userWithRole('Jugendleiter');
        $tab = DashboardTab::factory()->for($user)->create();
        DashboardTabWidget::factory()->for($tab, 'tab')->create(['widget' => MyTodosWidget::class]);

        Livewire::test(Dashboard::class)
            ->assertSet('currentWidgets', fn ($widgets): bool => $widgets->isEmpty())
            ->assertDontSeeLivewire(MyTodosWidget::class);
    }

    public function test_tab_management_actions_are_only_visible_in_edit_mode(): void
    {
        $this->userWithRole();

        Livewire::test(Dashboard::class)
            ->assertActionHidden('createTab')
            ->assertActionHidden('addWidget')
            ->assertActionHidden('renameTab')
            ->callAction('toggleEditing')
            ->assertActionVisible('createTab')
            ->assertActionVisible('addWidget')
            ->assertActionVisible('renameTab')
            ->assertActionHidden('deleteTab');
    }

    public function test_user_can_create_rename_and_delete_tabs(): void
    {
        $user = $this->userWithRole();

        $page = $this->editingDashboard()
            ->callAction('createTab', ['name' => 'Werkstatt'])
            ->assertHasNoActionErrors();

        $tab = $user->dashboardTabs()->where('name', 'Werkstatt')->sole();
        $page->assertSet('activeTab', $tab->id);

        $page->callAction('renameTab', ['name' => 'Büro'])->assertHasNoActionErrors();
        $this->assertSame('Büro', $tab->fresh()->name);

        $page->callAction('deleteTab');
        $this->assertModelMissing($tab);
        $this->assertSame(1, $user->dashboardTabs()->count());
    }

    public function test_tab_name_is_required(): void
    {
        $this->userWithRole();

        $this->editingDashboard()
            ->callAction('createTab', ['name' => ''])
            ->assertHasActionErrors(['name' => 'required']);
    }

    public function test_last_tab_cannot_be_deleted(): void
    {
        $user = $this->userWithRole();

        $this->editingDashboard()->assertActionHidden('deleteTab');

        $this->assertSame(1, $user->dashboardTabs()->count());
    }

    public function test_max_tabs_is_respected(): void
    {
        $user = $this->userWithRole();
        DashboardTab::factory()->for($user)->count(2)->create();

        filament('filament-dashboard-tabs')->maxTabs(2);

        $this->editingDashboard()->assertActionDisabled('createTab');
    }

    public function test_user_can_add_widget_with_width(): void
    {
        $user = $this->userWithRole();
        $tab = DashboardTab::factory()->for($user)->create();

        $this->editingDashboard()
            ->callAction('addWidget', ['widget' => MyTodosWidget::class, 'width' => WidgetWidth::Half->value])
            ->assertHasNoActionErrors()
            ->assertSet('currentWidgets', fn ($widgets): bool => $widgets->pluck('widget')->all() === [MyTodosWidget::class]);

        $widget = $tab->widgets()->sole();
        $this->assertSame(MyTodosWidget::class, $widget->widget);
        $this->assertSame(WidgetWidth::Half, $widget->width);
    }

    public function test_widget_already_on_the_tab_cannot_be_added_again(): void
    {
        $user = $this->userWithRole();
        $tab = DashboardTab::factory()->for($user)->create();
        DashboardTabWidget::factory()->for($tab, 'tab')->create(['widget' => MyTodosWidget::class]);

        $this->editingDashboard()
            ->callAction('addWidget', ['widget' => MyTodosWidget::class, 'width' => 'full'])
            ->assertHasActionErrors(['widget']);

        $this->assertSame(1, $tab->widgets()->count());
    }

    public function test_widget_without_permission_cannot_be_added(): void
    {
        $user = $this->userWithRole('Jugendleiter');
        $tab = DashboardTab::factory()->for($user)->create();

        $this->editingDashboard()
            ->callAction('addWidget', ['widget' => MyTodosWidget::class, 'width' => 'full'])
            ->assertHasActionErrors(['widget']);

        $this->assertSame(0, $tab->widgets()->count());
    }

    public function test_widgets_can_be_reordered(): void
    {
        $user = $this->userWithRole();
        $tab = DashboardTab::factory()->for($user)->create();
        $first = DashboardTabWidget::factory()->for($tab, 'tab')->create(['widget' => TimeClockWidget::class, 'sort' => 0]);
        $second = DashboardTabWidget::factory()->for($tab, 'tab')->create(['widget' => MyTodosWidget::class, 'sort' => 1]);
        $third = DashboardTabWidget::factory()->for($tab, 'tab')->create(['widget' => MyOrdersWidget::class, 'sort' => 2]);

        $this->editingDashboard()->call('sortWidgets', $third->id, 0);

        $this->assertSame([$third->id, $first->id, $second->id], $tab->widgets()->pluck('id')->all());
    }

    public function test_tabs_can_be_reordered(): void
    {
        $user = $this->userWithRole();
        $first = DashboardTab::factory()->for($user)->create(['sort' => 0]);
        $second = DashboardTab::factory()->for($user)->create(['sort' => 1]);

        $this->editingDashboard()->call('sortTabs', $second->id, 0);

        $this->assertSame([$second->id, $first->id], $user->dashboardTabs()->pluck('id')->all());
    }

    public function test_widget_width_can_be_toggled(): void
    {
        $user = $this->userWithRole();
        $tab = DashboardTab::factory()->for($user)->create();
        $widget = DashboardTabWidget::factory()->for($tab, 'tab')->create(['widget' => MyTodosWidget::class]);

        $page = $this->editingDashboard()->call('setWidth', $widget->id, 'half');
        $this->assertSame(WidgetWidth::Half, $widget->fresh()->width);

        $page->call('setWidth', $widget->id, 'full');
        $this->assertSame(WidgetWidth::Full, $widget->fresh()->width);
    }

    public function test_invalid_width_is_rejected(): void
    {
        $user = $this->userWithRole();
        $tab = DashboardTab::factory()->for($user)->create();
        $widget = DashboardTabWidget::factory()->for($tab, 'tab')->create(['widget' => MyTodosWidget::class]);

        $this->editingDashboard()
            ->call('setWidth', $widget->id, 'third')
            ->assertHasErrors(['width']);

        $this->assertSame(WidgetWidth::Full, $widget->fresh()->width);
    }

    public function test_widget_can_be_removed(): void
    {
        $user = $this->userWithRole();
        $tab = DashboardTab::factory()->for($user)->create();
        $widget = DashboardTabWidget::factory()->for($tab, 'tab')->create(['widget' => MyTodosWidget::class]);

        $this->editingDashboard()->call('removeWidget', $widget->id);

        $this->assertModelMissing($widget);
    }

    public function test_widget_can_be_moved_to_another_tab(): void
    {
        $user = $this->userWithRole();
        $source = DashboardTab::factory()->for($user)->create(['sort' => 0]);
        $target = DashboardTab::factory()->for($user)->create(['sort' => 1]);
        $widget = DashboardTabWidget::factory()->for($source, 'tab')->create(['widget' => MyTodosWidget::class]);

        $this->editingDashboard()
            ->callAction(TestAction::make('moveWidget')->arguments(['widget' => $widget->id]), ['target' => $target->id])
            ->assertHasNoActionErrors();

        $this->assertSame($target->id, $widget->fresh()->dashboard_tab_id);
    }

    public function test_widget_cannot_be_moved_to_a_tab_that_already_has_it(): void
    {
        $user = $this->userWithRole();
        $source = DashboardTab::factory()->for($user)->create(['sort' => 0]);
        $target = DashboardTab::factory()->for($user)->create(['sort' => 1]);
        $widget = DashboardTabWidget::factory()->for($source, 'tab')->create(['widget' => MyTodosWidget::class]);
        DashboardTabWidget::factory()->for($target, 'tab')->create(['widget' => MyTodosWidget::class]);

        $this->editingDashboard()->assertActionHidden(TestAction::make('moveWidget')->arguments(['widget' => $widget->id]));
    }

    public function test_switching_tabs_shows_that_tabs_widgets(): void
    {
        $user = $this->userWithRole();
        $first = DashboardTab::factory()->for($user)->create(['sort' => 0]);
        $second = DashboardTab::factory()->for($user)->create(['sort' => 1]);
        DashboardTabWidget::factory()->for($first, 'tab')->create(['widget' => TimeClockWidget::class]);
        DashboardTabWidget::factory()->for($second, 'tab')->create(['widget' => MyTodosWidget::class]);

        Livewire::test(Dashboard::class)
            ->assertSeeLivewire(TimeClockWidget::class)
            ->assertDontSeeLivewire(MyTodosWidget::class)
            ->set('activeTab', $second->id)
            ->assertSet('currentWidgets', fn ($widgets): bool => $widgets->pluck('widget')->all() === [MyTodosWidget::class]);
    }

    public function test_foreign_tab_id_falls_back_to_own_first_tab(): void
    {
        $user = $this->userWithRole();
        $own = DashboardTab::factory()->for($user)->create();
        $foreign = DashboardTab::factory()->create();

        Livewire::withQueryParams(['tab' => $foreign->id])
            ->test(Dashboard::class)
            ->assertSet('currentTab.id', $own->id);
    }

    /**
     * @return array<string, array{string, list<mixed>}>
     */
    public static function foreignRecordCalls(): array
    {
        return [
            'sortWidgets' => ['sortWidgets', ['widget', 0]],
            'sortTabs' => ['sortTabs', ['tab', 0]],
            'setWidth' => ['setWidth', ['widget', 'half']],
            'removeWidget' => ['removeWidget', ['widget']],
        ];
    }

    #[DataProvider('foreignRecordCalls')]
    public function test_foreign_records_cannot_be_changed(string $method, array $parameters): void
    {
        $this->userWithRole();
        $foreignTab = DashboardTab::factory()->for(User::factory())->create();
        $foreignWidget = DashboardTabWidget::factory()->for($foreignTab, 'tab')->create(['widget' => MyTodosWidget::class, 'width' => 'full']);

        $parameters = array_map(fn (mixed $parameter): mixed => match ($parameter) {
            'widget' => $foreignWidget->id,
            'tab' => $foreignTab->id,
            default => $parameter,
        }, $parameters);

        $this->editingDashboard()->call($method, ...$parameters)->assertNotFound();

        $this->assertModelExists($foreignWidget);
        $this->assertSame(WidgetWidth::Full, $foreignWidget->fresh()->width);
    }

    public function test_user_without_role_cannot_access_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/cockpit')->assertForbidden();
    }
}
