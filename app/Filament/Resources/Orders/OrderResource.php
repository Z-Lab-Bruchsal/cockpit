<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderProcessing;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ShoppingBag;

    protected static ?string $navigationLabel = 'Bestellungen';

    protected static ?string $pluralLabel = 'Bestellungen';

    protected static ?string $pluralModelLabel = 'Bestellungen';

    protected static ?string $modelLabel = 'Bestellung';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }

    /**
     * People who place orders see how many requests are still open.
     */
    public static function getNavigationBadge(): ?string
    {
        if (! OrderProcessing::canProcess(User::find(filament()->auth()->id()))) {
            return null;
        }

        $count = Order::query()->toProcess()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Noch zu bestellen';
    }

    /**
     * The list filtered to the requests that still have to be ordered.
     */
    public static function processingUrl(): string
    {
        return static::getUrl('index', ['filters' => [
            'abarbeiten' => ['isActive' => true],
            'mine' => ['isActive' => false],
        ]]);
    }
}
