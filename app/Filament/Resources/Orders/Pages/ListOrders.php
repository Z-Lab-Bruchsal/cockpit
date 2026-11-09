<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Exports\OrderExporter;
use App\Filament\Pages\OrderReportsPage;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('report')
                ->label('Bestellbericht')
                ->icon(Heroicon::OutlinedDocumentChartBar)
                ->color('gray')
                ->url(fn (): string => OrderReportsPage::getUrl())
                ->visible(fn (): bool => OrderReportsPage::canAccess()),
            ExportAction::make()->label('exportieren')->exporter(OrderExporter::class)->enableVisibleTableColumnsByDefault()->visible(fn () => User::find(filament()->auth()->user()->id)->can('Orders:Export')),
        ];
    }
}
