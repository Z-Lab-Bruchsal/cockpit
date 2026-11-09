<?php

namespace Tests\Feature\Orders;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
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
use Livewire\Features\SupportTesting\Testable;
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

    protected function listOrders(): Testable
    {
        return Livewire::test(ListOrders::class)->removeTableFilters();
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

    public function test_processing_filter_shows_only_open_requests(): void
    {
        $open = Order::factory()->create(['name' => 'Lötzinn']);
        $placed = Order::factory()->ordered()->create(['name' => 'Schon bestellt']);

        $this->actingAs($this->boss);

        $this->get(OrderResource::processingUrl())->assertOk()->assertSee('Lötzinn')->assertDontSee('Schon bestellt');

        $this->listOrders()
            ->assertCanSeeTableRecords([$open, $placed])
            ->filterTable('abarbeiten')
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$placed])
            ->assertSee('neu: Reichelt');

        $this->assertSame('1', OrderResource::getNavigationBadge());
    }

    public function test_processing_filter_and_badge_need_permission(): void
    {
        Order::factory()->create();
        $crew = User::factory()->create();
        $crew->givePermissionTo(Permission::findOrCreate('ViewAny:Order'));

        $this->actingAs($crew);

        Livewire::test(ListOrders::class)->assertTableFilterHidden('abarbeiten');
        $this->assertNull(OrderResource::getNavigationBadge());
    }

    public function test_ordered_opens_the_form_for_merchant_price_date_and_number(): void
    {
        $order = Order::factory()->create(['url' => 'https://www.reichelt.de/loetzinn-p1.html']);

        $this->actingAs($this->boss);

        $this->listOrders()
            ->mountAction(TestAction::make('bestellt_single')->table($order))
            ->assertMountedActionModalSee('Neuer Shop (reichelt.de)');

        $merchant = Merchant::query()->create(['name' => 'Reichelt']);

        $this->listOrders()
            ->callAction(TestAction::make('bestellt_single')->table($order), [
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

        $this->listOrders()
            ->mountAction(TestAction::make('bestellt_single')->table($next))
            ->assertSchemaStateSet(['merchant_id' => $merchant->id], 'mountedActionSchema0');
    }

    public function test_new_merchant_is_created_from_the_ordered_form(): void
    {
        $order = Order::factory()->create(['url' => 'https://www.reichelt.de/loetzinn-p1.html']);
        Merchant::query()->create(['name' => 'Amazon']);

        $this->actingAs($this->boss);

        $createMerchant = [
            TestAction::make('bestellt_single')->table($order),
            TestAction::make('createOption')->schemaComponent('merchant_id'),
        ];

        $this->listOrders()
            ->callAction($createMerchant, ['name' => 'Amazon', 'domain' => 'reichelt.de'])
            ->assertHasFormErrors(['name' => 'unique']);

        $this->listOrders()
            ->callAction($createMerchant, ['name' => 'Reichelt', 'domain' => 'Reichelt.de'])
            ->assertHasNoFormErrors();

        $merchant = Merchant::query()->where('name', 'Reichelt')->firstOrFail();

        $this->assertSame(['reichelt.de'], $merchant->domains()->pluck('domain')->all());
    }

    public function test_ordered_without_processing_permission_only_changes_the_status(): void
    {
        $order = Order::factory()->create();
        $editor = User::factory()->create();

        foreach (['ViewAny:Order', 'View:Order', 'Update:Order'] as $permission) {
            $editor->givePermissionTo(Permission::findOrCreate($permission));
        }

        $this->actingAs($editor);

        Livewire::test(ListOrders::class)
            ->removeTableFilters()
            ->callAction(TestAction::make('bestellt_single')->table($order))
            ->assertHasNoFormErrors();

        $this->assertSame('bestellt', $this->statusName($order));
        $this->assertNull($order->fresh()->merchant_id);
        $this->assertNotNull($order->fresh()->orderdatetime);
    }

    public function test_invalid_prices_are_rejected(): void
    {
        $order = Order::factory()->create();
        $merchant = Merchant::factory()->create();

        $this->actingAs($this->boss);

        $this->listOrders()
            ->callAction(TestAction::make('bestellt_single')->table($order), [
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
        $alreadyPlaced = Order::factory()->ordered(['order_number' => 'ALT-1'])->create(['url' => 'https://www.amazon.de/c']);

        $this->actingAs($this->boss);

        $this->listOrders()
            ->selectTableRecords([$first, $second, $alreadyPlaced])
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
        $this->assertSame('ALT-1', $alreadyPlaced->fresh()->order_number);
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

    public function test_recording_a_request_leaves_the_order_date_empty(): void
    {
        $this->boss->givePermissionTo(Permission::findOrCreate('Create:Order'));
        $this->actingAs($this->boss);

        Livewire::test(CreateOrder::class)
            ->assertFormFieldHidden('orderdatetime')
            ->assertFormFieldHidden('merchant_id')
            ->fillForm([
                'name' => 'Lötzinn',
                'count' => 2,
                'url' => 'https://www.reichelt.de/loetzinn.html',
                'description' => 'Bleifrei, 1 mm',
                'user_id' => $this->boss->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $order = Order::query()->sole();
        $this->assertNull($order->orderdatetime);
        $this->assertNull($order->merchant_id);
        $this->assertSame('Bleifrei, 1 mm', $order->description);
        $this->assertSame('erfasst', $this->statusName($order));
    }

    public function test_just_ordered_sets_the_date_and_the_status(): void
    {
        $this->travelTo('2026-11-09 14:37:12');
        $order = Order::factory()->create();
        $this->actingAs($this->boss);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertSchemaStateSet(['orderdatetime' => null])
            ->callAction(TestAction::make('justOrdered')->schemaComponent('orderdatetime'))
            ->assertSchemaStateSet(['orderdatetime' => '2026-11-09 14:37'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('2026-11-09 14:37', $order->fresh()->orderdatetime->format('Y-m-d H:i'));
        $this->assertSame('bestellt', $this->statusName($order));
    }

    public function test_ordered_header_action_sets_the_date(): void
    {
        $this->travelTo('2026-11-09 15:20:00');
        $order = Order::factory()->create();
        $this->actingAs($this->boss);

        $merchant = Merchant::factory()->create();

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('bestellt_single', [
                'merchant_id' => $merchant->id,
                'orderdatetime' => now(),
                'order_number' => 'B-1',
                'total_price' => '3,20',
            ])
            ->assertHasNoFormErrors()
            ->assertSchemaStateSet(['orderdatetime' => '2026-11-09 15:20', 'order_number' => 'B-1', 'merchant_id' => $merchant->id]);

        $this->assertSame('2026-11-09 15:20', $order->fresh()->orderdatetime->format('Y-m-d H:i'));
        $this->assertSame('bestellt', $this->statusName($order));
    }

    public function test_order_date_can_be_edited_without_processing_permission(): void
    {
        $order = Order::factory()->ordered()->create();
        $editor = User::factory()->create();

        foreach (['ViewAny:Order', 'View:Order', 'Update:Order'] as $permission) {
            $editor->givePermissionTo(Permission::findOrCreate($permission));
        }

        $this->actingAs($editor);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertFormFieldHidden('merchant_id')
            ->fillForm(['orderdatetime' => '2026-10-01 09:15:00'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('2026-10-01 09:15', $order->fresh()->orderdatetime->format('Y-m-d H:i'));
    }

    public function test_new_order_mail_links_to_the_open_orders(): void
    {
        $order = Order::factory()->create(['description' => 'Bleifrei, 1 mm']);

        $mail = new OrderRegistered($order);

        $this->assertSame(OrderResource::processingUrl(), $mail->content()->with['url_process']);
        $this->assertStringContainsString('filters', $mail->content()->with['url_process']);
        $mail->assertSeeInHtml('offenen Bestellungen');
        $mail->assertSeeInHtml('Bleifrei, 1 mm');
    }
}
