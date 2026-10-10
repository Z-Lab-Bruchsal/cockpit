<?php

namespace App\Filament\Resources\Chips\Tables;

use App\Models\Chip;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChipsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_seen_at', 'desc')
            ->columns([
                TextColumn::make('uid')
                    ->label('Chip-ID')
                    ->fontFamily('mono')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('user.name')
                    ->label('Benutzer')
                    ->placeholder('unbekannt')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('label')
                    ->label('Bezeichnung')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Aktiv')
                    ->boolean(),
                TextColumn::make('last_seen_at')
                    ->label('Zuletzt gesehen')
                    ->since()
                    ->dateTimeTooltip()
                    ->placeholder('nie')
                    ->sortable(),
                TextColumn::make('lastSeenTerminal.name')
                    ->label('Terminal'),
                TextColumn::make('first_seen_at')
                    ->label('Erstmals gesehen')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('unassigned')
                    ->label('Nur unbekannte Chips')
                    ->query(fn (Builder $query): Builder => $query->whereNull('user_id')),
                TernaryFilter::make('is_active')
                    ->label('Aktiv'),
            ])
            ->recordActions([
                Action::make('assign')
                    ->label('Zuordnen')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->visible(fn (Chip $record): bool => $record->user_id === null)
                    ->authorize('update')
                    ->schema([
                        Select::make('user_id')
                            ->label('Benutzer')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->action(function (Chip $record, array $data): void {
                        $record->update(['user_id' => $data['user_id']]);

                        Notification::make()
                            ->title('Chip zugeordnet')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
