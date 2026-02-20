<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use App\Models\Orderstatus;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Grouping\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Erstellungsdatum')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Änderungsdatum')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('url')
                    ->label('URL')
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => substr($state, 0, 40))
                    ->toggleable(isToggledHiddenByDefault: false),
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
            ])->striped()
            ->selectable()
            ->groups([
                Group::make('orderstatus.name')->label('Bestellstatus'),
                Group::make('user.name')->label('Bestellt von'),
            ])
            ->filters([
                SelectFilter::make('orderstatus_id')->relationship('orderstatus', 'name')->label("Bestellstatus")->multiple(),
                Filter::make('mine')->label("meine")->query(fn (Builder $query): Builder => $query->where('user_id', filament()->auth()->user()))->default(function() {
                    if(filament()->auth()->user()->id == 1) return false;
                    return true;
                }),
                Filter::make('alleoffenen')
                    ->default()
                    ->label("alle offenen")
                    ->query(function (Builder $query) {
                        $orderstatusGenommen = Orderstatus::where("name", "genommen")->first();
                        $query->where('orderstatus_id', '<', $orderstatusGenommen->id);
                        return $query;
                    }),
            ])
            ->persistFiltersInSession()
            ->persistColumnsInSession()
            ->recordActions([
                Action::make('bestellt_single')
                    ->icon(Heroicon::ShoppingCart)->iconButton()->label("Bestellt")->action(function(Model $record) {
                        $orderstatusBestellt = Orderstatus::where("name", "bestellt")->first();
                        $record->orderstatus_id = $orderstatusBestellt->id;
                        $record->save();
                    })->visible(function(Model $record) {$orderstatusErfasst = Orderstatus::where("name", "erfasst")->first();return ($orderstatusErfasst->id == $record->orderstatus_id);}),
                Action::make('angekommen_single')
                    ->icon(Heroicon::BuildingOffice)->iconButton()->label("Angekommen")->action(function(Model $record) {
                        $orderstatusAngekommen = Orderstatus::where("name", "angekommen")->first();
                        $record->orderstatus_id = $orderstatusAngekommen->id;
                        $record->save();
                    })->visible(function(Model $record) {$orderstatusBestellt = Orderstatus::where("name", "bestellt")->first();return ($orderstatusBestellt->id == $record->orderstatus_id);}),
                Action::make('genommen_single')
                    ->icon(Heroicon::Check)->iconButton()->label("Genommen")->action(function(Model $record) {
                        $orderstatusGenommen = Orderstatus::where("name", "genommen")->first();
                        $record->orderstatus_id = $orderstatusGenommen->id;
                        $record->save();
                    })->visible(function(Model $record) {$orderstatusAngekommen = Orderstatus::where("name", "angekommen")->first();return ($orderstatusAngekommen->id == $record->orderstatus_id);}),
                Action::make("url_oeffnen")->icon(Heroicon::Link)->iconButton()->label("URL öffnen")->url(function (Model $record) { return $record->url;}, true),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    Action::make('bestellt_bulk')
                        ->label("Ausgewählte bestellt")
                        ->accessSelectedRecords()
                        ->action(function (Collection $selectedRecords) {
                            $orderstatusBestellt = Orderstatus::where("name", "bestellt")->first();
                            $selectedRecords->each(
                                fn (Model $selectedRecord) => $selectedRecord->update([
                                    'orderstatus_id' => $orderstatusBestellt->id
                                ]),
                            );
                        })->requiresConfirmation()->icon(Heroicon::ShoppingCart),
                    Action::make('angekommen_bulk')
                        ->label("Ausgewählte angekommen")
                        ->accessSelectedRecords()
                        ->action(function (Collection $selectedRecords) {
                            $orderstatusAngekommen = Orderstatus::where("name", "angekommen")->first();
                            $selectedRecords->each(
                                fn (Model $selectedRecord) => $selectedRecord->update([
                                    'orderstatus_id' => $orderstatusAngekommen->id
                                ]),
                            );
                        })->requiresConfirmation()->icon(Heroicon::BuildingOffice),
                ]),
            ]);
    }
}
