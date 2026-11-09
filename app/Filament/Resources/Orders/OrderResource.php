<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ProcessOrders;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Navigation\NavigationItem;
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
            'process' => ProcessOrders::route('/abarbeiten'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }

    /**
     * Besides the list, people who place orders get the work list with the number of open requests.
     *
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        return [
            ...parent::getNavigationItems(),
            NavigationItem::make('Bestellungen abarbeiten')
                ->group(static::getNavigationGroup())
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->sort((static::getNavigationSort() ?? 0) + 1)
                ->url(fn (): string => ProcessOrders::getUrl())
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.process'))
                ->badge(fn (): ?string => ($count = ProcessOrders::openCount()) > 0 ? (string) $count : null)
                ->visible(fn (): bool => ProcessOrders::canAccess()),
        ];
    }
}
