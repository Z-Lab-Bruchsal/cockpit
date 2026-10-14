<?php

namespace App\Filament\Resources\Kids\Tables;

use App\Models\Kid;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KidsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Erstellt')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Bearbeitet')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('age')
                    ->label('Alter')
                    ->state(fn (Kid $record): ?int => $record->age())
                    ->suffix(' Jahre')
                    ->placeholder('—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('birthdate', $direction === 'asc' ? 'desc' : 'asc')),
                TextColumn::make('contacts.name')
                    ->label('Eltern / Kontakte')
                    ->badge()
                    ->separator(',')
                    ->searchable(),
                TextColumn::make('interests.name')
                    ->label('Interessen')
                    ->badge()
                    ->separator(','),
                TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('interests')
                    ->label('Interessen')
                    ->relationship('interests', 'name')
                    ->multiple()
                    ->preload(),
                Filter::make('age')
                    ->label('Alter')
                    ->schema([
                        TextInput::make('min')->label('Alter von')->numeric(),
                        TextInput::make('max')->label('Alter bis')->numeric(),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['min'] ?? null), fn (Builder $query) => $query->whereDate('birthdate', '<=', now()->subYears((int) $data['min'])))
                        ->when(filled($data['max'] ?? null), fn (Builder $query) => $query->whereDate('birthdate', '>', now()->subYears((int) $data['max'] + 1)))),
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
