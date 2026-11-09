<?php

namespace Tests\Feature\Orders;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ProcessOrders;
use App\Mail\OrderRegistered;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Orderstatus;
use App\Models\User;
use App\Services\Orders\MerchantResolver;
use App\Services\Orders\OrderProcessing;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OrderProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected User $boss;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();

        foreach (['erfasst', 'bestellt', 'angekommen', 'genommen'] as $name) {
            Orderstatus::query()->create(['name' => $name]);
        }

        $this->boss = User::factory()->create();

        foreach (['ViewAny:Order', 'View:Order', 'Update:Order', 'Orders:Process'] as $permission) {
            $this->boss->givePermissionTo(Permission::findOrCreate($permission));
        }
    }

    protected function statusName(Order $order): string
    {
        return $order->fresh()->orderstatus->name;
    }

    public function test_merchant_is_derived_from_the_link(): void
    {
        $amazon = Merchant::factory()->withDomain('amazon.de')->create(['name' => 'Amazon']);
        $amazonUk = Merchant::factory()->withDomain('amazon.co.uk')->create(['name' => 'Amazon UK']);

        $this->assertSame('reichelt.de', MerchantResolver::host('https://www.reichelt.de/x.html?ARTICLE=1'));
        $this->assertSame('reichelt.de', MerchantResolver::host('www.Reichelt.de/x'));
        $this->assertNull(MerchantResolver::host('kein link'));
        $this->assertNull(MerchantResolver::host(null));

        $this->assertSame('amazon.de', MerchantResolver::registrableDomain('https://smile.amazon.de/dp/1'));
        $this->assertSame('amazon.co.uk', MerchantResolver::registrableDomain('https://shop.amazon.co.uk/dp/1'));

        $this->assertTrue($amazon->is(MerchantResolver::forUrl('https://www.amazon.de/dp/B000')));
        $this->assertTrue($amazon->is(MerchantResolver::forUrl('https://smile.amazon.de/dp/B000')));
        $this->assertTrue($amazonUk->is(MerchantResolver::forUrl('https://www.amazon.co.uk/dp/B000')));
        $this->assertNull(MerchantResolver::forUrl('https://www.reichelt.de/x'));

        $this->assertSame('Reichelt', MerchantResolver::suggestName('https://www.reichelt.de/x'));
        $this->assertSame('Amazon', MerchantResolver::suggestName('https://www.amazon.co.uk/x'));
    }

    public function test_prices_are_parsed_in_german_and_english_notation(): void
    {
        $this->assertSame(12.99, OrderProcessing::parsePrice('12,99'));
        $this->assertSame(12.99, OrderProcessing::parsePrice('12.99'));
        $this->assertSame(1234.5, OrderProcessing::parsePrice('1.234,50'));
        $this->assertSame(7.0, OrderProcessing::parsePrice('7 €'));
        $this->assertNull(OrderProcessing::parsePrice(''));
        $this->assertNull(OrderProcessing::parsePrice('abc'));
    }

    public function test_work_list_shows_only_open_requests(): void
    {
        $open = Order::factory()->create(['name' => 'Lötzinn']);
        $placed = Order::factory()->ordered()->create(['name' => 'Schon bestellt']);

        $this->actingAs($this->boss);

        $this->get(ProcessOrders::getUrl())->assertOk()->assertSee('Lötzinn');

        Livewire::test(ProcessOrders::class)
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$placed])
            ->assertSee('neu: Reichelt');
    }

    public function test_work_list_needs_permission(): void
    {
        $crew = User::factory()->create();
        $crew->givePermissionTo(Permission::findOrCreate('ViewAny:Order'));

        $this->actingAs($crew)->get(ProcessOrders::getUrl())->assertForbidden();
    }

    public function test_recording_an_order_with_a_new_merchant_remembers_its_domain(): void
    {
        $order = Order::factory()->create(['url' => 'https://www.reichelt.de/loetzinn-p1.html']);

        $this->actingAs($this->boss);

        Livewire::test(ProcessOrders::class)
            ->mountTableAction('markOrdered', $order)
            ->assertMountedActionModalSee('Neuer Shop (reichelt.de)');

        $merchant = Merchant::query()->create(['name' => 'Reichelt']);

        Livewire::test(ProcessOrders::class)
            ->callAction(TestAction::make('markOrdered')->table($order), [
                'merchant_id' => $merchant->id,
                'orderdatetime' => '2026-11-09 10:30:00',
                'order_number' => 'R-4711',
                'total_price' => '12,99',
            ])
            ->assertHasNoFormErrors()
            ->assertNotified('Als bestellt erfasst');

        $order->refresh();

        $this->assertSame('bestellt', $order->orderstatus->name);
        $this->assertSame($merchant->id, $order->merchant_id);
        $this->assertSame('R-4711', $order->order_number);
        $this->assertSame('12.99', $order->total_price);
        $this->assertSame('2026-11-09 10:30', $order->orderdatetime->format('Y-m-d H:i'));
        $this->assertSame($this->boss->id, $order->processed_by_user_id);
        $this->assertSame(['reichelt.de'], $merchant->domains()->pluck('domain')->all());

        $next = Order::factory()->create(['url' => 'https://reichelt.de/anderes']);

        Livewire::test(ProcessOrders::class)
            ->mountTableAction('markOrdered', $next)
            ->assertTableActionDataSet(['merchant_id' => $merchant->id]);
    }

    public function test_invalid_prices_are_rejected(): void
    {
        $order = Order::factory()->create();
        $merchant = Merchant::factory()->create();

        $this->actingAs($this->boss);

        Livewire::test(ProcessOrders::class)
            ->callAction(TestAction::make('markOrdered')->table($order), [
                'merchant_id' => $merchant->id,
                'orderdatetime' => '2026-11-09 10:30:00',
                'total_price' => 'zwölf Euro',
            ])
            ->assertHasFormErrors(['total_price' => 'regex']);

        $this->assertSame('erfasst', $this->statusName($order));
    }

    public function test_orders_placed_together_share_merchant_and_number(): void
    {
        $merchant = Merchant::factory()->withDomain('amazon.de')->create(['name' => 'Amazon']);
        $first = Order::factory()->create(['url' => 'https://www.amazon.de/a']);
        $second = Order::factory()->create(['url' => 'https://www.amazon.de/b']);

        $this->actingAs($this->boss);

        Livewire::test(ProcessOrders::class)
            ->selectTableRecords([$first, $second])
            ->callAction(TestAction::make('markOrderedTogether')->table()->bulk(), [
                'merchant_id' => $merchant->id,
                'orderdatetime' => '2026-11-08 09:00:00',
                'order_number' => '302-123',
                'prices' => [
                    ['order_id' => $first->id, 'name' => 'A', 'total_price' => '5,00'],
                    ['order_id' => $second->id, 'name' => 'B', 'total_price' => '7.50'],
                ],
            ])
            ->assertHasNoFormErrors();

        $this->assertSame(['302-123', '302-123'], [$first->fresh()->order_number, $second->fresh()->order_number]);
        $this->assertSame(['5.00', '7.50'], [$first->fresh()->total_price, $second->fresh()->total_price]);
        $this->assertSame(['bestellt', 'bestellt'], [$this->statusName($first), $this->statusName($second)]);
    }

    public function test_quick_status_change_still_sets_the_order_date_but_keeps_an_entered_one(): void
    {
        $quick = Order::factory()->create();
        $quick->update(['orderstatus_id' => Orderstatus::query()->where('name', 'bestellt')->value('id')]);

        $this->assertNotNull($quick->fresh()->orderdatetime);

        $processed = Order::factory()->create();
        OrderProcessing::markOrdered($processed, ['merchant_id' => Merchant::factory()->create()->id, 'orderdatetime' => '2026-01-02 08:00:00'], $this->boss);

        $this->assertSame('2026-01-02 08:00', $processed->fresh()->orderdatetime->format('Y-m-d H:i'));
    }

    public function test_processors_can_correct_the_data_on_the_edit_page(): void
    {
        $merchant = Merchant::factory()->create();
        $order = Order::factory()->ordered(['total_price' => 10])->create();
        $this->boss->givePermissionTo(Permission::findOrCreate('Update:Order'));

        $this->actingAs($this->boss);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertSchemaStateSet(['total_price' => '10,00'])
            ->fillForm(['merchant_id' => $merchant->id, 'total_price' => '11,50', 'order_number' => 'NEU-1'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('11.50', $order->fresh()->total_price);
        $this->assertSame('NEU-1', $order->fresh()->order_number);
        $this->assertSame($merchant->id, $order->fresh()->merchant_id);
    }

    public function test_new_order_mail_links_to_the_work_list(): void
    {
        $order = Order::factory()->create();

        $mail = new OrderRegistered($order);

        $this->assertSame(ProcessOrders::getUrl(), $mail->content()->with['url_process']);
        $mail->assertSeeInHtml('Abarbeitungsliste');
    }
}
