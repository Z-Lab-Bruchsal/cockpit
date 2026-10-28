<?php

namespace Tests\Feature\Orders;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Widgets\MyOrdersWidget;
use App\Http\Controllers\OrderController;
use App\Mail\OrderRegistered;
use App\Models\Order;
use App\Models\Orderstatus;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OrderStatusLinkTest extends TestCase
{
    use RefreshDatabase;

    protected User $orderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();

        foreach (['erfasst', 'bestellt', 'angekommen', 'genommen'] as $name) {
            Orderstatus::query()->create(['name' => $name]);
        }

        $this->orderer = User::factory()->create();
    }

    protected function createOrder(string $status = 'erfasst'): Order
    {
        return Order::query()->create([
            'name' => 'Filament PLA',
            'url' => 'https://shop.example.com/pla',
            'count' => 2,
            'user_id' => $this->orderer->id,
            'orderstatus_id' => Orderstatus::query()->where('name', $status)->value('id'),
        ])->refresh();
    }

    protected function userWithOrderPermissions(array $abilities): User
    {
        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:Order"));
        }

        return $user;
    }

    protected function statusName(Order $order): string
    {
        return $order->fresh()->orderstatus->name;
    }

    public function test_old_unsigned_links_no_longer_change_the_status(): void
    {
        $order = $this->createOrder();

        $this->get('/updateOrderstatusOrdered/'.$order->public_uuid)->assertNotFound();
        $this->get(route('orders.status.show', ['order' => $order->public_uuid, 'status' => 'bestellt']))->assertForbidden();
        $this->post(route('orders.status.update', ['order' => $order->public_uuid, 'status' => 'bestellt']))->assertForbidden();

        $this->assertSame('erfasst', $this->statusName($order));
    }

    public function test_opening_the_mail_link_only_shows_a_confirmation(): void
    {
        $order = $this->createOrder();
        $mailUrl = OrderController::statusUrl($order, 'bestellt');

        $this->get($mailUrl)
            ->assertOk()
            ->assertSee('Jetzt auf „Bestellt" setzen', false)
            ->assertSee('Filament PLA');

        $this->assertSame('erfasst', $this->statusName($order));
    }

    public function test_confirming_sets_the_status(): void
    {
        $order = $this->createOrder();

        $this->get(OrderController::statusUrl($order, 'bestellt'))->assertOk();
        $this->post($this->signedUpdateUrl($order, 'bestellt'))->assertRedirect();

        $this->assertSame('bestellt', $this->statusName($order));
    }

    public function test_status_is_never_moved_backwards(): void
    {
        $order = $this->createOrder('genommen');

        $this->get(OrderController::statusUrl($order, 'bestellt'))
            ->assertOk()
            ->assertSee('Hier ist nichts mehr zu tun.');
        $this->post($this->signedUpdateUrl($order, 'bestellt'));

        $this->assertSame('genommen', $this->statusName($order));
    }

    public function test_links_expire(): void
    {
        $order = $this->createOrder();
        $mailUrl = OrderController::statusUrl($order, 'bestellt');

        $this->travel(OrderController::LINK_LIFETIME_DAYS + 1)->days();

        $this->get($mailUrl)->assertForbidden();
    }

    public function test_tampered_status_is_rejected(): void
    {
        $order = $this->createOrder();

        $this->get(str_replace('/bestellt', '/genommen', OrderController::statusUrl($order, 'bestellt')))->assertForbidden();
    }

    public function test_order_mail_contains_signed_link(): void
    {
        $this->orderer->givePermissionTo(Permission::findOrCreate('Orders:GetNotifications'));
        $order = $this->createOrder();

        $mail = Mail::sent(OrderRegistered::class)->sole();
        $url = $mail->content()->with['url_ordered'];

        $this->assertStringContainsString('/bestellung/'.$order->public_uuid.'/bestellt', $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_status_actions_require_update_permission(): void
    {
        $order = $this->createOrder();
        $this->actingAs($this->userWithOrderPermissions(['ViewAny', 'View']));

        Livewire::test(ListOrders::class)
            ->removeTableFilters()
            ->assertActionHidden(TestAction::make('bestellt_single')->table($order));

        $this->assertSame('erfasst', $this->statusName($order));
    }

    public function test_status_actions_work_with_update_permission(): void
    {
        $order = $this->createOrder();
        $this->actingAs($this->userWithOrderPermissions(['ViewAny', 'View', 'Update']));

        Livewire::test(ListOrders::class)
            ->removeTableFilters()
            ->callAction(TestAction::make('bestellt_single')->table($order));

        $this->assertSame('bestellt', $this->statusName($order));
    }

    public function test_bulk_status_action_skips_orders_without_update_permission(): void
    {
        $order = $this->createOrder();
        $this->actingAs($this->userWithOrderPermissions(['ViewAny', 'View']));

        Livewire::test(ListOrders::class)
            ->removeTableFilters()
            ->selectTableRecords([$order])
            ->callAction(TestAction::make('bestellt_bulk')->table()->bulk());

        $this->assertSame('erfasst', $this->statusName($order));
    }

    public function test_unsafe_order_url_is_not_linked(): void
    {
        $order = $this->createOrder();
        $order->update(['url' => 'javascript:alert(1)']);
        $this->actingAs($this->userWithOrderPermissions(['ViewAny', 'View', 'Update']));

        Livewire::test(EditOrder::class, ['record' => $order->id])
            ->assertActionDoesNotHaveUrl('url_oeffnen', 'javascript:alert(1)');
    }

    public function test_orders_widget_requires_view_any_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('View:MyOrdersWidget'));
        $this->actingAs($user);

        $this->assertFalse(MyOrdersWidget::canView());

        $user->givePermissionTo(Permission::findOrCreate('ViewAny:Order'));

        $this->assertTrue(MyOrdersWidget::canView());
    }

    protected function signedUpdateUrl(Order $order, string $status): string
    {
        return URL::temporarySignedRoute('orders.status.update', now()->addHour(), ['order' => $order->public_uuid, 'status' => $status]);
    }
}
