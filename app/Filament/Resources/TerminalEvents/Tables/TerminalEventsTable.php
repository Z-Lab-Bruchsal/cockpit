<?php

namespace App\Filament\Resources\TerminalEvents\Tables;

use App\Enums\TerminalEventResult;
use App\Enums\TerminalEventType;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TerminalEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Zeitpunkt')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                TextColumn::make('terminal.name')
                    ->label('Terminal')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Benutzer')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('chip_uid')
                    ->label('Chip-ID')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Art')
                    ->badge(),
                TextColumn::make('result')
                    ->label('Ergebnis')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('terminal_id')
                    ->label('Terminal')
                    ->relationship('terminal', 'name'),
                SelectFilter::make('user_id')
                    ->label('Benutzer')
                    ->relationship('user', 'name')
                    ->searchable(),
                SelectFilter::make('type')
                    ->label('Art')
                    ->options(TerminalEventType::class),
                SelectFilter::make('result')
                    ->label('Ergebnis')
                    ->options(TerminalEventResult::class)
                    ->multiple(),
            ]);
    }
}
