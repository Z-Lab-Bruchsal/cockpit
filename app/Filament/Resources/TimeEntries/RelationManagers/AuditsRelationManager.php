<?php

namespace App\Filament\Resources\TimeEntries\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditsRelationManager extends RelationManager
{
    protected static string $relationship = 'audits';

    protected static ?string $title = 'Änderungsprotokoll';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('changed_at', 'desc')
            ->columns([
                TextColumn::make('changed_at')
                    ->label('Zeitpunkt')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('action')
                    ->label('Aktion')
                    ->badge(),
                TextColumn::make('field')
                    ->label('Feld'),
                TextColumn::make('old_value')
                    ->label('Alter Wert')
                    ->wrap(),
                TextColumn::make('new_value')
                    ->label('Neuer Wert')
                    ->wrap(),
                TextColumn::make('changedBy.name')
                    ->label('Geändert von'),
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ]);
    }
}
