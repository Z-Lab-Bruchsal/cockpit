<?php

namespace App\Filament\Resources\Todos\Tables;

use App\Filament\Actions\DoneTodoAction;
use App\Filament\Actions\ReopenTodoAction;
use App\Filament\Actions\SetReminderAction;
use App\Models\Group;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TodosTable
{
    public static function configure(Table $table): Table
    {
        return $table
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
                Filter::make('alleoffenen')
                    ->default()
                    ->label('nicht erledigt')
                    ->query(function (Builder $query) {
                        $query->whereNull('done_date');

                        return $query;
                    }),
                Filter::make('mine')->label('von mir oder für mich')
                    ->default()
                    ->query(function (Builder $query) {
                        $query->where(function (Builder $query) {
                            $query
                                ->orWhere('user_id', filament()->auth()->user()->id)
                                ->orWhere('todoable_type', User::class)->where('todoable_id', filament()->auth()->user()->id)
                                ->orwhere('todoable_type', Group::class)->whereIn('todoable_id', User::find(filament()->auth()->user()->id)->groups()->get()->pluck('id'));

                            return $query;
                        });

                        return $query;
                    }),
                Filter::make('keineZukuenftigeWiedervorlage')
                    ->label('ohne zukünftige Wiedervorlage')
                    ->default()
                    ->query(function (Builder $query) {
                        $query->where(function (Builder $query) {
                            $query
                                ->whereNull('follow_up')
                                ->orWhereDate('follow_up', '<=', today());

                            return $query;
                        });

                        return $query;
                    }),

            ])
            ->recordActions([
                DoneTodoAction::make('done'),
                ReopenTodoAction::make('reopen'),
                SetReminderAction::make([]),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton()
                    ->visible(function (Model $record) {
                        return $record->is_owner_or_assigned();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
