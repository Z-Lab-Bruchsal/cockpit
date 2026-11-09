<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Orders\Actions\MarkOrderedAction;
use App\Filament\Resources\Orders\Schemas\OrderProcessingFields;
use App\Models\Order;
use App\Models\Orderstatus;
use App\Models\User;
use App\Services\Orders\MerchantResolver;
use App\Services\Orders\OrderProcessing;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Produktname')
                    ->description(fn (Model $record): ?string => Str::limit((string) $record->description, 80) ?: null)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('url')
                    ->label('URL')
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => substr($state, 0, 40))
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('merchant')
                    ->label('Händler')
                    ->state(fn (Order $record): string => $record->merchant?->name
                        ?? MerchantResolver::forUrl($record->url)?->name
                        ?? 'neu: '.(MerchantResolver::suggestName($record->url) ?? '–'))
                    ->badge()
                    ->color(fn (string $state): string => str_starts_with($state, 'neu:') ? 'warning' : 'gray')
                    ->visible(fn (): bool => static::canProcess())
                    ->toggleable(),
                TextColumn::make('count')
                    ->label('Anzahl')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('orderstatus.name')
                    ->label('Bestellstatus')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('user.name')
                    ->label('Bestellt von')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('created_at')
                    ->label('Erstellt')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Bearbeitet')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])->striped()->defaultSort('upated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['merchant', 'orderstatus', 'user']))
            ->groups([
                Group::make('orderstatus.name')->label('Bestellstatus'),
                Group::make('user.name')->label('Bestellt von'),
            ])
            ->filters([
                Filter::make('abarbeiten')
                    ->label('Bestellungen abarbeiten')
                    ->query(fn (Builder $query): Builder => $query->toProcess())
                    ->visible(fn (): bool => static::canProcess()),
                SelectFilter::make('orderstatus_id')->relationship('orderstatus', 'name')->label('Bestellstatus')->multiple(),
                Filter::make('mine')->label('meine')
                    ->default()
                    ->query(function (Builder $query) {
                        $query->where('user_id', filament()->auth()->user()->id);

                        return $query;
                    }),
                Filter::make('alleoffenen')
                    ->default()
                    ->label('alle offenen')
                    ->query(function (Builder $query) {
                        $orderstatusGenommen = Orderstatus::where('name', 'genommen')->first();
                        $query->where('orderstatus_id', '!=', $orderstatusGenommen->id);

                        return $query;
                    }),
            ])
            ->recordActions([
                MarkOrderedAction::make()->iconButton(),
                Action::make('angekommen_single')
                    ->authorize('update')
                    ->icon(Heroicon::BuildingOffice)->iconButton()->label('Angekommen')->action(function (Model $record) {
                        $orderstatusAngekommen = Orderstatus::where('name', 'angekommen')->first();
                        $record->orderstatus_id = $orderstatusAngekommen->id;
                        $record->save();
                    })->visible(function (Model $record) {
                        $orderstatusBestellt = Orderstatus::where('name', 'bestellt')->first();

                        return $orderstatusBestellt->id == $record->orderstatus_id;
                    }),
                Action::make('genommen_single')
                    ->authorize('update')
                    ->icon(Heroicon::Check)->iconButton()->label('Genommen')->action(function (Model $record) {
                        $orderstatusGenommen = Orderstatus::where('name', 'genommen')->first();
                        $record->orderstatus_id = $orderstatusGenommen->id;
                        $record->save();
                    })->visible(function (Model $record) {
                        $orderstatusAngekommen = Orderstatus::where('name', 'angekommen')->first();

                        return $orderstatusAngekommen->id == $record->orderstatus_id;
                    }),
                Action::make('url_oeffnen')->icon(Heroicon::Link)->iconButton()->label('URL öffnen')->url(function (Model $record) {
                    return Str::sanitizeUrl($record->url);
                }, true),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    static::markOrderedTogetherAction(),
                    Action::make('bestellt_bulk')
                        ->hidden(fn (): bool => static::canProcess())
                        ->authorizeIndividualRecords('update')
                        ->label('Ausgewählte bestellt')
                        ->accessSelectedRecords()
                        ->action(function (Collection $selectedRecords) {
                            $orderstatusBestellt = Orderstatus::where('name', 'bestellt')->first();
                            $selectedRecords->each(
                                fn (Model $selectedRecord) => $selectedRecord->update([
                                    'orderstatus_id' => $orderstatusBestellt->id,
                                ]),
                            );
                        })->requiresConfirmation()->icon(Heroicon::ShoppingCart),
                    Action::make('angekommen_bulk')
                        ->authorizeIndividualRecords('update')
                        ->label('Ausgewählte angekommen')
                        ->accessSelectedRecords()
                        ->action(function (Collection $selectedRecords) {
                            $orderstatusAngekommen = Orderstatus::where('name', 'angekommen')->first();
                            $selectedRecords->each(
                                fn (Model $selectedRecord) => $selectedRecord->update([
                                    'orderstatus_id' => $orderstatusAngekommen->id,
                                ]),
                            );
                        })->requiresConfirmation()->icon(Heroicon::BuildingOffice),
                ]),
            ]);
    }

    /**
     * Several requests placed in one order: merchant, date and order number are shared, the price is entered per article.
     */
    protected static function markOrderedTogetherAction(): BulkAction
    {
        return BulkAction::make('markOrderedTogether')
            ->label('Gemeinsam bestellt')
            ->icon(Heroicon::ShoppingCart)
            ->visible(fn (): bool => static::canProcess())
            ->authorizeIndividualRecords('update')
            ->modalHeading('In einer Bestellung bestellt')
            ->modalDescription('Händler, Datum und Bestellnummer gelten für alle ausgewählten Artikel, der Preis wird je Artikel eingetragen. Schon bestellte Artikel werden übersprungen.')
            ->fillForm(fn (Collection $records): array => [
                'merchant_id' => MerchantResolver::forUrl($records->first()?->url)?->id,
                'orderdatetime' => now(),
                'prices' => static::openRecords($records)->map(fn (Order $order): array => [
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
                $openRecords = static::openRecords($records);
                $user = User::find(filament()->auth()->id());

                $openRecords->each(fn (Order $order) => OrderProcessing::markOrdered($order, [
                    ...$data,
                    'total_price' => $prices[$order->id] ?? null,
                ], $user));

                Notification::make()->success()->title($openRecords->count().' Artikel als bestellt erfasst')->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * @param  Collection<int, Order>  $records
     * @return Collection<int, Order>
     */
    protected static function openRecords(Collection $records): Collection
    {
        return $records->filter(fn (Order $order): bool => $order->orderstatus?->name === 'erfasst')->values();
    }

    protected static function canProcess(): bool
    {
        return OrderProcessing::canProcess(User::find(filament()->auth()->id()));
    }
}
