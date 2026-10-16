<?php

namespace App\Filament\Resources\Todos\Tables;

use App\Filament\Actions\DoneTodoAction;
use App\Filament\Actions\ReopenTodoAction;
use App\Filament\Actions\SetReminderAction;
use App\Models\Todo;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
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
                TextColumn::make('assignees')
                    ->label('Zugewiesen')
                    ->state(fn (Todo $record): string => $record->assigneeNames())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $query) => $query
                        ->whereHas('assignedUsers', fn (Builder $users) => $users->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('assignedGroups', fn (Builder $groups) => $groups->where('name', 'like', "%{$search}%")))),
                TextColumn::make('due_date')
                    ->label('Todo-Datum')
                    ->date()
                    ->sortable(),
                TextColumn::make('done_date')
                    ->label('Erledigt am')
                    ->date()
                    ->sortable(),
                TextColumn::make('completion_progress')
                    ->label('Fortschritt')
                    ->tooltip('Erledigt von x der Zugeordneten (wenn jeder für sich erledigt)')
                    ->state(fn (Todo $record): ?string => $record->completionProgress()),
                TextColumn::make('follow_up')
                    ->label('Wiedervorlage')
                    ->date()
                    ->sortable(),
                IconColumn::make('review')
                    ->label('Review')
                    ->boolean(),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['assignedUsers', 'assignedGroups']))
            ->filters([
                Filter::make('alleoffenen')
                    ->default()
                    ->label('nicht erledigt')
                    ->query(fn (Builder $query): Builder => $query->openFor(filament()->auth()->user())),
                Filter::make('mine')->label('von mir oder für mich')
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->relevantFor(filament()->auth()->user())),
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
                    ->visible(fn (Todo $record): bool => $record->isOwnerOrAssignee(filament()->auth()->user())),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
