<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Pages\OrderReportsPage;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Schemas\OrderProcessingFields;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\MerchantResolver;
use App\Services\Orders\OrderProcessing;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Work list for the boss: requested orders that still have to be placed. Recording one asks for merchant,
 * date, order number and price, which end up in the report for accounting.
 */
class ProcessOrders extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.resources.orders.pages.process-orders';

    protected static ?string $title = 'Bestellungen abarbeiten';

    protected static ?string $breadcrumb = 'Abarbeiten';

    public static function canAccess(array $parameters = []): bool
    {
        return static::currentUser()?->can('Orders:Process') ?? false;
    }

    public static function openCount(): int
    {
        return Order::query()->toProcess()->count();
    }

    protected static function currentUser(): ?User
    {
        /** @var User|null $user */
        $user = filament()->auth()->user();

        return $user;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Order::query()->toProcess()->with('user'))
            ->defaultSort('url')
            ->emptyStateHeading('Alles bestellt')
            ->emptyStateDescription('Es gibt keine offenen Bestellwünsche.')
            ->columns([
                TextColumn::make('name')
                    ->label('Artikel')
                    ->searchable()
                    ->url(fn (Order $record): ?string => Str::sanitizeUrl($record->url), shouldOpenInNewTab: true)
                    ->description(fn (Order $record): ?string => MerchantResolver::host($record->url)),
                TextColumn::make('merchant')
                    ->label('Händler')
                    ->state(fn (Order $record): string => MerchantResolver::forUrl($record->url)?->name ?? 'neu: '.(MerchantResolver::suggestName($record->url) ?? '–'))
                    ->badge()
                    ->color(fn (string $state): string => str_starts_with($state, 'neu:') ? 'warning' : 'gray'),
                TextColumn::make('count')
                    ->label('Anzahl')
                    ->numeric(),
                TextColumn::make('user.name')
                    ->label('Angefragt von'),
                TextColumn::make('created_at')
                    ->label('Angefragt am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('markOrdered')
                    ->label('Als bestellt erfassen')
                    ->icon(Heroicon::ShoppingCart)
                    ->modalHeading(fn (Order $record): string => 'Bestellt: '.$record->name)
                    ->schema(OrderProcessingFields::make(OrderProcessingFields::orderUrl(...)))
                    ->action(function (Order $record, array $data): void {
                        OrderProcessing::markOrdered($record, $data, static::currentUser());

                        Notification::make()->success()->title('Als bestellt erfasst')->send();
                    }),
                Action::make('openUrl')
                    ->label('Link öffnen')
                    ->icon(Heroicon::Link)
                    ->iconButton()
                    ->url(fn (Order $record): ?string => Str::sanitizeUrl($record->url), shouldOpenInNewTab: true),
            ])
            ->toolbarActions([
                BulkAction::make('markOrderedTogether')
                    ->label('Gemeinsam bestellt')
                    ->icon(Heroicon::ShoppingCart)
                    ->modalHeading('In einer Bestellung bestellt')
                    ->modalDescription('Händler, Datum und Bestellnummer gelten für alle ausgewählten Artikel, der Preis wird je Artikel eingetragen.')
                    ->fillForm(fn (Collection $records): array => [
                        'merchant_id' => MerchantResolver::forUrl($records->first()?->url)?->id,
                        'orderdatetime' => now(),
                        'prices' => $records->map(fn (Order $order): array => [
                            'order_id' => $order->id,
                            'name' => $order->count.' × '.$order->name,
                            'total_price' => null,
                        ])->values()->all(),
                    ])
                    ->schema(fn (Collection $records): array => [
                        ...OrderProcessingFields::make(fn (?Model $record): ?string => $records->first()?->url, withPrice: false),
                        Repeater::make('prices')
                            ->label('Preise')
                            ->schema([
                                Hidden::make('order_id'),
                                OrderProcessingFields::price()
                                    ->label(fn (Get $get): string => (string) $get('name'))
                                    ->helperText(null),
                                Hidden::make('name'),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $prices = collect($data['prices'] ?? [])->pluck('total_price', 'order_id');

                        $records->each(fn (Order $order) => OrderProcessing::markOrdered($order, [
                            ...$data,
                            'total_price' => $prices[$order->id] ?? null,
                        ], static::currentUser()));

                        Notification::make()->success()->title($records->count().' Artikel als bestellt erfasst')->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('report')
                ->label('Bericht')
                ->icon(Heroicon::OutlinedDocumentChartBar)
                ->color('gray')
                ->url(fn (): string => OrderReportsPage::getUrl())
                ->visible(fn (): bool => OrderReportsPage::canAccess()),
        ];
    }
}
