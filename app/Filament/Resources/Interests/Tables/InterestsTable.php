<?php

namespace App\Filament\Resources\Interests\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class InterestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                ColorColumn::make('color')
                    ->label('Farbe'),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),
                TextColumn::make('courses_count')
                    ->label('Kurse')
                    ->counts('courses')
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Aktiv'),
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
