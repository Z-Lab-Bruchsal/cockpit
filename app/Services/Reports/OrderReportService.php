<?php

namespace App\Services\Reports;

use App\Models\CompanySetting;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
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
     * @var list<string>
     */
    public const HEADINGS = ['Bestellt am', 'Händler', 'Bestellnummer', 'Artikel', 'Anzahl', 'Preis (€)', 'Angefragt von', 'Bestellt von', 'Link'];

    /**
     * Orders placed from $from (inclusive) until $to (exclusive), oldest first.
     * For whole days pass the start of the first and the start of the day after the last day, see forDays().
     *
     * @return Collection<int, Order>
     */
    public function orders(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Order::query()
            ->with(['merchant', 'user', 'processedBy'])
            ->where('orderdatetime', '>=', $from)
            ->where('orderdatetime', '<', $to)
            ->orderBy('orderdatetime')
            ->orderBy('id')
            ->get();
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
     * @return list<array<int, string|int|float|null>>
     */
    public function rows(CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->orders($from, $to)
            ->map(fn (Order $order): array => [
                $order->orderdatetime?->format('d.m.Y'),
                $order->merchant?->name,
                $order->order_number,
                $order->name,
                $order->count,
                $order->total_price !== null ? (float) $order->total_price : null,
                $order->user?->name,
                $order->processedBy?->name,
                $order->url,
            ])
            ->values()
            ->all();
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
    public function xlsx(CarbonInterface $from, CarbonInterface $to): string
    {
        $path = $this->temporaryPath('xlsx');
        $orders = $this->orders($from, $to);

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(self::HEADINGS, (new Style)->setFontBold()));

        foreach ($this->rows($from, $to) as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->addRow(Row::fromValues([]));

        foreach ($this->totalsByMerchant($orders) as $merchant => $total) {
            $writer->addRow(Row::fromValues(['Summe', $merchant, null, null, null, $total]));
        }

        $writer->addRow(Row::fromValues(['Gesamt', null, null, null, null, round((float) $orders->sum('total_price'), 2)], (new Style)->setFontBold()));
        $writer->close();

        return $path;
    }

    /**
     * CSV for Excel: UTF-8 with BOM, semicolons and decimal commas.
     */
    public function csv(CarbonInterface $from, CarbonInterface $to): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::HEADINGS, ';', escape: '');

        foreach ($this->rows($from, $to) as $row) {
            $row[5] = $row[5] === null ? null : number_format($row[5], 2, ',', '');
            fputcsv($handle, $row, ';', escape: '');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    public function pdf(CarbonInterface $from, CarbonInterface $to): string
    {
        $orders = $this->orders($from, $to);

        return Pdf::loadView('pdf.orders.report', [
            'company' => CompanySetting::current(),
            'from' => $from,
            'to' => $to,
            'orders' => $orders,
            'totals' => $this->totalsByMerchant($orders),
            'total' => round((float) $orders->sum('total_price'), 2),
            'money' => static fn (?float $amount): string => $amount === null ? '–' : (string) Number::currency($amount, 'EUR', 'de'),
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')->output();
    }

    protected function temporaryPath(string $extension): string
    {
        $directory = storage_path('app/private/reports');
        File::ensureDirectoryExists($directory);

        return $directory.'/'.Str::uuid().'.'.$extension;
    }
}
