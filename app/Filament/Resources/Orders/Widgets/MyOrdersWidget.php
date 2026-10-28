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
        $user = User::find(filament()->auth()->id());

        return parent::canView() && $user->can('View:MyOrdersWidget') && $user->can('viewAny', Order::class);
    }

    public function table(Table $table): Table
    {
        return OrdersTable::configure($table)
            ->heading('Bestellungen')
            ->query(fn (): Builder => Order::query());
    }
}
