<?php

namespace App\Services\Reports;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\CompanySetting;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Report of placed orders for accounting: as Excel, CSV and PDF.
 */
class OrderReportService
{
    /**
     * Exportable columns in their fixed order; columns with default false are only exported when selected.
     *
     * @return array<string, array{label: string, default: bool, value: Closure(Order): (string|int|float|null)}>
     */
    public static function columns(): array
    {
        return [
            'ordered_at' => ['label' => 'Bestellt am', 'default' => true, 'value' => fn (Order $order): ?string => $order->orderdatetime?->format('d.m.Y')],
            'merchant' => ['label' => 'Händler', 'default' => true, 'value' => fn (Order $order): ?string => $order->merchant?->name],
            'order_number' => ['label' => 'Bestellnummer', 'default' => true, 'value' => fn (Order $order): ?string => $order->order_number],
            'name' => ['label' => 'Artikel', 'default' => true, 'value' => fn (Order $order): ?string => $order->name],
            'count' => ['label' => 'Anzahl', 'default' => true, 'value' => fn (Order $order): ?int => $order->count],
            'price' => ['label' => 'Preis (€)', 'default' => true, 'value' => fn (Order $order): ?float => $order->total_price !== null ? (float) $order->total_price : null],
            'cockpit_url' => ['label' => 'Link Cockpit', 'default' => true, 'value' => fn (Order $order): string => OrderResource::getUrl('edit', ['record' => $order], panel: 'admin')],
            'requested_by' => ['label' => 'Angefragt von', 'default' => false, 'value' => fn (Order $order): ?string => $order->user?->name],
            'ordered_by' => ['label' => 'Bestellt von', 'default' => false, 'value' => fn (Order $order): ?string => $order->processedBy?->name],
            'product_url' => ['label' => 'Link Produkt', 'default' => false, 'value' => fn (Order $order): ?string => $order->url],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function columnOptions(): array
    {
        return array_map(fn (array $column): string => $column['label'], static::columns());
    }

    /**
     * @return list<string>
     */
    public static function defaultColumns(): array
    {
        return array_keys(array_filter(static::columns(), fn (array $column): bool => $column['default']));
    }

    /**
     * Known keys of the selection in the fixed column order; no selection means the default columns.
     *
     * @param  array<int, string>|null  $columns
     * @return list<string>
     */
    public static function resolveColumns(?array $columns): array
    {
        $selected = array_values(array_intersect(array_keys(static::columns()), $columns ?? []));

        return $selected === [] ? static::defaultColumns() : $selected;
    }

    /**
     * @param  array<int, string>|null  $columns
     * @return list<string>
     */
    public function headings(?array $columns = null): array
    {
        $labels = static::columnOptions();

        return array_map(fn (string $key): string => $labels[$key], static::resolveColumns($columns));
    }

    /**
     * Orders placed from $from (inclusive) until $to (exclusive), oldest first.
     * For whole days pass the start of the first and the start of the day after the last day, see forDays().
     *
     * @return Collection<int, Order>
     */
    public function orders(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return static::query($from, $to)
            ->orderBy('orderdatetime')
            ->orderBy('id')
            ->get();
    }

    /**
     * Orders placed from $from (inclusive) until $to (exclusive), unsorted.
     *
     * @return Builder<Order>
     */
    public static function query(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Order::query()
            ->with(['merchant', 'user', 'processedBy'])
            ->where('orderdatetime', '>=', $from)
            ->where('orderdatetime', '<', $to);
    }

    /**
     * Sum per merchant, by name.
     *
     * @param  Collection<int, Order>  $orders
     * @return array<string, float>
     */
    public function totalsByMerchant(Collection $orders): array
    {
        return $orders
            ->groupBy(fn (Order $order): string => $order->merchant?->name ?? 'Ohne Händler')
            ->map(fn (Collection $group): float => round((float) $group->sum('total_price'), 2))
            ->sortKeys()
            ->all();
    }

    /**
     * @param  array<int, string>|null  $columns
     * @return list<list<string|int|float|null>>
     */
    public function rows(CarbonInterface $from, CarbonInterface $to, ?array $columns = null): array
    {
        $definitions = static::columns();
        $keys = static::resolveColumns($columns);

        return $this->orders($from, $to)
            ->map(fn (Order $order): array => array_map(fn (string $key): string|int|float|null => $definitions[$key]['value']($order), $keys))
            ->values()
            ->all();
    }

    /**
     * Position of the price among the selected columns, null when it is not selected.
     *
     * @param  array<int, string>|null  $columns
     */
    public static function priceIndex(?array $columns = null): ?int
    {
        $index = array_search('price', static::resolveColumns($columns), true);

        return $index === false ? null : $index;
    }

    /**
     * Period covering the given days completely.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public static function forDays(CarbonInterface $firstDay, CarbonInterface $lastDay): array
    {
        return [$firstDay->copy()->startOfDay(), $lastDay->copy()->addDay()->startOfDay()];
    }

    public function filename(CarbonInterface $from, CarbonInterface $to, string $extension): string
    {
        return 'bestellungen-'.$from->format('Y-m-d').'-bis-'.$to->copy()->subSecond()->format('Y-m-d').'.'.$extension;
    }

    /**
     * Writes the Excel file and returns its path in the temp directory.
     */
    /**
     * @param  array<int, string>|null  $columns
     */
    public function xlsx(CarbonInterface $from, CarbonInterface $to, ?array $columns = null): string
    {
        $path = $this->temporaryPath('xlsx');
        $orders = $this->orders($from, $to);
        $priceIndex = static::priceIndex($columns);

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($this->headings($columns), (new Style)->setFontBold()));

        foreach ($this->rows($from, $to, $columns) as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        if ($priceIndex !== null) {
            $writer->addRow(Row::fromValues([]));

            foreach ($this->totalsByMerchant($orders) as $merchant => $total) {
                $writer->addRow(Row::fromValues($this->totalRow($priceIndex, $total, 'Summe', $merchant)));
            }

            $writer->addRow(Row::fromValues($this->totalRow($priceIndex, round((float) $orders->sum('total_price'), 2), 'Gesamt'), (new Style)->setFontBold()));
        }

        $writer->close();

        return $path;
    }

    /**
     * CSV for Excel: UTF-8 with BOM, semicolons and decimal commas.
     */
    /**
     * @param  array<int, string>|null  $columns
     */
    public function csv(CarbonInterface $from, CarbonInterface $to, ?array $columns = null): string
    {
        $priceIndex = static::priceIndex($columns);
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $this->headings($columns), ';', escape: '');

        foreach ($this->rows($from, $to, $columns) as $row) {
            if ($priceIndex !== null && $row[$priceIndex] !== null) {
                $row[$priceIndex] = number_format($row[$priceIndex], 2, ',', '');
            }

            fputcsv($handle, $row, ';', escape: '');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * @param  array<int, string>|null  $columns
     */
    public function pdf(CarbonInterface $from, CarbonInterface $to, ?array $columns = null): string
    {
        $orders = $this->orders($from, $to);

        return Pdf::loadView('pdf.orders.report', [
            'company' => CompanySetting::current(),
            'from' => $from,
            'to' => $to,
            'orders' => $orders,
            'columns' => static::resolveColumns($columns),
            'headings' => $this->headings($columns),
            'rows' => $this->rows($from, $to, $columns),
            'priceIndex' => static::priceIndex($columns),
            'totals' => $this->totalsByMerchant($orders),
            'total' => round((float) $orders->sum('total_price'), 2),
            'money' => static fn (?float $amount): string => $amount === null ? '–' : (string) Number::currency($amount, 'EUR', 'de'),
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')->output();
    }

    /**
     * Sum row with the amount in the price column and the labels in front of it.
     *
     * @return list<string|float|null>
     */
    protected function totalRow(int $priceIndex, float $total, string $label, ?string $merchant = null): array
    {
        $row = array_fill(0, $priceIndex + 1, null);
        $row[$priceIndex] = $total;

        if ($priceIndex >= 2) {
            $row[0] = $label;
            $row[1] = $merchant;
        } elseif ($priceIndex === 1) {
            $row[0] = trim($label.' '.$merchant);
        }

        return $row;
    }

    protected function temporaryPath(string $extension): string
    {
        $directory = storage_path('app/private/reports');
        File::ensureDirectoryExists($directory);

        return $directory.'/'.Str::uuid().'.'.$extension;
    }
}
