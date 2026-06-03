<?php

namespace App\Filament\Resources\Todos\Tables;

use App\Models\Group;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TodosTable
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
                    ->label('Bearbeitet')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Benutzer')
                    ->searchable(),
                TextColumn::make('todoable.name')
                    ->label('Zugewiesen')
                    ->searchable(),
                TextColumn::make('due_date')
                    ->label('Todo-Datum')
                    ->date()
                    ->sortable(),
                TextColumn::make('done_date')
                    ->label('Erledigt am')
                    ->date()
                    ->sortable(),
                TextColumn::make('follow_up')
                    ->label('Wiedervorlage')
                    ->date()
                    ->sortable(),
                IconColumn::make('review')
                    ->label('Review')
                    ->boolean(),
            ])
            ->filters([
                Filter::make('mine')->label('von mir oder für mich')
                    ->default()
                    ->query(function (Builder $query) {
                        $query->where('user_id', filament()->auth()->user()->id)
                            ->orWhere('todoable_type', User::class)->where('todoable_id', filament()->auth()->user()->id)
                            ->orwhere('todoable_type', Group::class)->whereIn('todoable_id', User::find(filament()->auth()->user()->id)->groups()->get()->pluck('id'));
                        return $query;
                    }),
                Filter::make('alleoffenen')
                    ->default()
                    ->label('nicht erledigt')
                    ->query(function (Builder $query) {
                        $query->where('done_date', null);
                        return $query;
                    }),

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
