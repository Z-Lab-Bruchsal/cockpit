<?php

namespace App\Filament\Resources\CourseTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CourseTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),
                IconColumn::make('is_default')
                    ->label('Standard')
                    ->boolean(),
                TextColumn::make('card_style')
                    ->label('Visitenkarte')
                    ->badge(),
                TextColumn::make('detail_layout')
                    ->label('Detailseite')
                    ->badge(),
                ColorColumn::make('accent_color')
                    ->label('Akzent'),
                TextColumn::make('courses_count')
                    ->label('Kurse')
                    ->counts('courses'),
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
