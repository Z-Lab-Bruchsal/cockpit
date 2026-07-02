<?php

namespace App\Filament\Resources\Orders\Widgets;

use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class MyOrdersWidget extends TableWidget
{
    protected int|string|array $columnSpan = 2;

    public function table(Table $table): Table
    {
        return OrdersTable::configure($table)
            ->heading('Bestellungen')
            ->query(fn (): Builder => Order::query());
    }
}
