<?php

namespace App\Filament\Exports;

use App\Models\Order;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class OrderExporter extends Exporter
{
    protected static ?string $model = Order::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('created_at')->label('Erstallungsdatum'),
            ExportColumn::make('updated_at')->label('Letzte Veränderung'),
            ExportColumn::make('name')->label('Name'),
            ExportColumn::make('url')->label('URL'),
            ExportColumn::make('count')->label('Anzahl'),
            ExportColumn::make('orderstatus.name')->label('Bestellstatus'),
            ExportColumn::make('orderdatetime')->label('Bestelldatum'),
            ExportColumn::make('user.name')->label('Bestellt von'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your order export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
