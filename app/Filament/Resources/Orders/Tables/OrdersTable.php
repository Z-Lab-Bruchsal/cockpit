<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Orderstatus;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('url')
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => substr($state, 0, 40)) ,
                TextColumn::make('count')
                    ->numeric()
                    ->sortable(),
                SelectColumn::make('orderstatus')->options(Orderstatus::class)
                    ->searchable(),
                TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
            ])->striped()
            ->selectable()
            ->filters([
                //
            ])
            ->recordActions([
                Action::make("url_oeffnen")->icon(Heroicon::Link)->iconButton()->label("URL öffnen")->url(function (Model $record) { return $record->url;}, true),
                EditAction::make()->iconButton()->label("Bearbeiten"),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    Action::make('bestellt')
                        ->accessSelectedRecords()
                        ->action(function (Collection $selectedRecords) {
                            $selectedRecords->each(
                                fn (Model $selectedRecord) => $selectedRecord->update([
                                    'orderstatus' => Orderstatus::ORDERED,
                                ]),
                            );
                        })->requiresConfirmation()->icon(Heroicon::ShoppingCart),
                ]),
            ]);
    }
}
