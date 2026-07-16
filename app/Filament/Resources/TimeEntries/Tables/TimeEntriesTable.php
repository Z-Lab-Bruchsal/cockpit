<?php

namespace App\Filament\Resources\TimeEntries\Tables;

use App\Enums\TimeEntryType;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TimeEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('happened_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Benutzer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Art')
                    ->badge()
                    ->sortable(),
                TextColumn::make('happened_at')
                    ->label('Zeitpunkt')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('note')
                    ->label('Notiz')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('recordedBy.name')
                    ->label('Erfasst von')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Benutzer')
                    ->options(fn () => User::query()
                        ->whereIn('id', filament()->auth()->user()->visibleUserIds())
                        ->pluck('name', 'id')),
                SelectFilter::make('type')
                    ->label('Art')
                    ->options(TimeEntryType::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
