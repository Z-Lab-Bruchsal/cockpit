<?php

namespace App\Filament\Resources\Orders\Widgets;

use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use App\Models\User;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Override;

class MyOrdersWidget extends TableWidget
{
    protected int|string|array $columnSpan = 2;

    #[Override]
    public static function canView(): bool
    {
        return parent::canView() && User::find(filament()->auth()->user()->id)->can("View:MyOrdersWidget");
    }

    public function table(Table $table): Table
    {
        return OrdersTable::configure($table)
            ->heading('Bestellungen')
            ->query(fn (): Builder => Order::query());
    }
}
