<?php

namespace Tests\Feature\Orders;

use App\Filament\Clusters\Settings\Pages\OrderReportSettingsPage;
use App\Filament\Pages\OrderReportsPage;
use App\Filament\Resources\Orders\OrderResource;
use App\Mail\OrderReportMail;
use App\Models\CompanySetting;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Orderstatus;
use App\Models\User;
use App\Services\Reports\OrderReportService;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class OrderReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();

        foreach (['erfasst', 'bestellt', 'angekommen', 'genommen'] as $name) {
            Orderstatus::query()->create(['name' => $name]);
        }
    }

    protected function seedOrders(): void
    {
        $reichelt = Merchant::factory()->create(['name' => 'Reichelt']);
        $amazon = Merchant::factory()->create(['name' => 'Amazon']);

        Order::factory()->ordered(['merchant_id' => $reichelt->id, 'orderdatetime' => '2026-11-02 09:00', 'total_price' => 10.5, 'order_number' => 'R-1', 'name' => 'Lötzinn'])->create();
        Order::factory()->ordered(['merchant_id' => $reichelt->id, 'orderdatetime' => '2026-11-08 23:59', 'total_price' => 4.5, 'name' => 'Litze'])->create();
        Order::factory()->ordered(['merchant_id' => $amazon->id, 'orderdatetime' => '2026-11-05 12:00', 'total_price' => 20, 'name' => 'Filament'])->create();
        Order::factory()->ordered(['merchant_id' => $amazon->id, 'orderdatetime' => '2026-11-09 00:00', 'total_price' => 99, 'name' => 'Nächste Woche'])->create();
        Order::factory()->create(['name' => 'Noch nicht bestellt']);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function lastWeek(): array
    {
        return OrderReportService::forDays(Carbon::parse('2026-11-02'), Carbon::parse('2026-11-08'));
    }

    public function test_report_contains_orders_of_the_period_with_totals(): void
    {
        $this->seedOrders();
        $reports = app(OrderReportService::class);
        [$from, $to] = $this->lastWeek();

        $orders = $reports->orders($from, $to);

        $this->assertSame(['Lötzinn', 'Filament', 'Litze'], $orders->pluck('name')->all());
        $this->assertSame(['Amazon' => 20.0, 'Reichelt' => 15.0], $reports->totalsByMerchant($orders));
        $this->assertSame(['02.11.2026', 'Reichelt', 'R-1', 'Lötzinn'], array_slice($reports->rows($from, $to)[0], 0, 4));
        $this->assertSame('bestellungen-2026-11-02-bis-2026-11-08.pdf', $reports->filename($from, $to, 'pdf'));
    }

    public function test_excel_csv_and_pdf_are_generated(): void
    {
        $this->seedOrders();
        $reports = app(OrderReportService::class);
        [$from, $to] = $this->lastWeek();

        $reader = new Reader;
        $reader->open($path = $reports->xlsx($from, $to));
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();
        unlink($path);

        $lotzinn = Order::query()->where('name', 'Lötzinn')->firstOrFail();

        $this->assertSame(['Bestellt am', 'Händler', 'Bestellnummer', 'Artikel', 'Anzahl', 'Preis (€)', 'Link Cockpit'], $rows[0]);
        $this->assertSame('Lötzinn', $rows[1][3]);
        $this->assertSame(OrderResource::getUrl('edit', ['record' => $lotzinn], panel: 'admin'), $rows[1][6]);
        $this->assertEquals(['Gesamt', '', '', '', '', 35], array_slice(end($rows), 0, 6));

        $csv = $reports->csv($from, $to);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('02.11.2026;Reichelt;R-1;Lötzinn;', $csv);
        $this->assertStringContainsString(';10,50;', $csv);

        $this->assertStringContainsString('/cockpit/orders/'.$lotzinn->id.'/edit', $csv);
        $this->assertStringNotContainsString('Angefragt von', $csv);

        $this->assertStringStartsWith('%PDF', $reports->pdf($from, $to));
    }

    public function test_export_contains_only_selected_columns(): void
    {
        $this->seedOrders();
        Order::query()->where('name', 'Lötzinn')->update(['url' => 'https://example.com/loetzinn']);
        $requester = Order::query()->where('name', 'Lötzinn')->firstOrFail()->user;
        $reports = app(OrderReportService::class);
        [$from, $to] = $this->lastWeek();

        $columns = ['product_url', 'unknown', 'requested_by', 'name'];

        $this->assertSame(['Artikel', 'Angefragt von', 'Link Produkt'], $reports->headings($columns));
        $this->assertSame(['Lötzinn', $requester?->name, 'https://example.com/loetzinn'], $reports->rows($from, $to, $columns)[0]);
        $this->assertNull(OrderReportService::priceIndex($columns));
        $this->assertSame(OrderReportService::defaultColumns(), OrderReportService::resolveColumns(['unknown']));

        $csv = $reports->csv($from, $to, $columns);
        $this->assertStringContainsString('Artikel;"Angefragt von";"Link Produkt"', $csv);
        $this->assertStringNotContainsString('Reichelt', $csv);

        $reader = new Reader;
        $reader->open($path = $reports->xlsx($from, $to, $columns));
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();
        unlink($path);

        $this->assertCount(4, $rows);
        $this->assertStringNotContainsString('Gesamt', json_encode($rows));

        $this->assertStringStartsWith('%PDF', $reports->pdf($from, $to, ['price', 'name']));
    }

    public function test_report_page_downloads_files(): void
    {
        $this->seedOrders();
        $this->seed(ShieldSeeder::class);
        $boss = User::factory()->create();
        $boss->assignRole('Z-Lab-Boss');

        $this->actingAs($boss)->get(OrderReportsPage::getUrl())->assertOk();

        Livewire::test(OrderReportsPage::class)
            ->fillForm(['from' => '2026-11-02', 'to' => '2026-11-08'])
            ->assertCanSeeTableRecords(Order::query()->whereIn('name', ['Lötzinn', 'Litze', 'Filament'])->get())
            ->assertCanNotSeeTableRecords(Order::query()->whereIn('name', ['Nächste Woche', 'Noch nicht bestellt'])->get())
            ->assertSee('35,00');

        foreach (['xlsx', 'csv', 'pdf'] as $format) {
            Livewire::test(OrderReportsPage::class)
                ->fillForm(['from' => '2026-11-02', 'to' => '2026-11-08'])
                ->callAction('download_'.$format, data: ['columns' => ['name', 'price', 'cockpit_url', 'ordered_by']])
                ->assertHasNoActionErrors()
                ->assertFileDownloaded('bestellungen-2026-11-02-bis-2026-11-08.'.$format);
        }

        Livewire::test(OrderReportsPage::class)
            ->fillForm(['from' => '2026-11-02', 'to' => '2026-11-08'])
            ->mountAction('download_xlsx')
            ->assertSchemaStateSet(['columns' => OrderReportService::defaultColumns()])
            ->fillForm(['columns' => []])
            ->callMountedAction()
            ->assertHasActionErrors(['columns' => 'required']);

        $this->actingAs(User::factory()->create())->get(OrderReportsPage::getUrl())->assertForbidden();
    }

    public function test_weekly_mail_is_sent_when_due_and_only_once(): void
    {
        $this->seedOrders();
        CompanySetting::current()->update([
            'order_report_enabled' => true,
            'order_report_weekday' => 1,
            'order_report_time' => '07:00',
            'order_report_recipients' => ['buchhaltung@example.com', 'kein-mail'],
        ]);

        $this->travelTo(Carbon::parse('2026-11-09 06:45'));
        $this->artisan('app:send-order-report')->assertSuccessful();
        Mail::assertNothingSent();

        $this->travelTo(Carbon::parse('2026-11-09 07:15'));
        $this->artisan('app:send-order-report')->assertSuccessful();

        Mail::assertSent(OrderReportMail::class, function (OrderReportMail $mail): bool {
            return $mail->hasTo('buchhaltung@example.com')
                && ! $mail->hasTo('kein-mail')
                && $mail->periodStart->format('Y-m-d H:i') === '2026-11-02 07:15'
                && count($mail->attachments()) === 2;
        });
        $this->assertSame('2026-11-09 07:15', CompanySetting::current()->order_report_last_sent_at->format('Y-m-d H:i'));

        $this->travelTo(Carbon::parse('2026-11-09 08:00'));
        $this->artisan('app:send-order-report')->assertSuccessful();
        Mail::assertSentCount(1);

        $this->travelTo(Carbon::parse('2026-11-10 08:00'));
        $this->artisan('app:send-order-report')->assertSuccessful();
        Mail::assertSentCount(1);

        $this->travelTo(Carbon::parse('2026-11-16 07:00'));
        $this->artisan('app:send-order-report')->assertSuccessful();
        Mail::assertSent(OrderReportMail::class, fn (OrderReportMail $mail): bool => $mail->periodStart->format('Y-m-d H:i') === '2026-11-09 07:15');
    }

    public function test_mail_lists_totals_and_handles_empty_weeks(): void
    {
        $this->seedOrders();
        [$from, $to] = $this->lastWeek();

        (new OrderReportMail($from, $to))
            ->assertSeeInHtml('3 Positionen wurden')
            ->assertSeeInHtml('Reichelt')
            ->assertSeeInHtml('35,00');

        (new OrderReportMail(Carbon::parse('2025-01-01'), Carbon::parse('2025-01-08')))
            ->assertSeeInHtml('nichts bestellt');
    }

    public function test_disabled_report_is_not_sent_unless_forced(): void
    {
        CompanySetting::current()->update(['order_report_enabled' => false, 'order_report_recipients' => ['chef@example.com']]);

        $this->artisan('app:send-order-report')->assertSuccessful();
        Mail::assertNothingSent();

        $this->artisan('app:send-order-report --force')->assertSuccessful();
        Mail::assertSent(OrderReportMail::class);
    }

    public function test_boss_configures_the_weekly_mail(): void
    {
        $this->seed(ShieldSeeder::class);
        $boss = User::factory()->create();
        $boss->assignRole('Z-Lab-Boss');
        $this->actingAs($boss);

        Livewire::test(OrderReportSettingsPage::class)
            ->fillForm([
                'order_report_enabled' => true,
                'order_report_weekday' => 5,
                'order_report_time' => '16:00',
                'order_report_recipients' => ['nope'],
            ])
            ->call('save')
            ->assertHasFormErrors(['order_report_recipients.0']);

        Livewire::test(OrderReportSettingsPage::class)
            ->fillForm([
                'order_report_enabled' => true,
                'order_report_weekday' => 5,
                'order_report_time' => '16:00',
                'order_report_recipients' => ['buchhaltung@example.com'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = CompanySetting::current();
        $this->assertTrue($settings->order_report_enabled);
        $this->assertSame(5, $settings->order_report_weekday);
        $this->assertSame(['buchhaltung@example.com'], $settings->order_report_recipients);
    }
}
