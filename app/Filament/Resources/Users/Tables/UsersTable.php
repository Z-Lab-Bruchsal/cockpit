<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Actions\SendPasswordResetLinkAction;
use App\Filament\Resources\Groups\RelationManagers\UsersRelationManager;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->hiddenOn(UsersRelationManager::class)
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->hiddenOn(UsersRelationManager::class)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('groups.name')
                    ->label('Gruppen')
                    ->toggleable(),
                TextColumn::make('roles.name')
                    ->label('Rollen')
                    ->toggleable(),
                IconColumn::make('team_visible')
                    ->label('Homepage')
                    ->boolean()
                    ->hiddenOn(UsersRelationManager::class)
                    ->toggleable(),
                TextColumn::make('team_area')
                    ->label('Teambereich')
                    ->hiddenOn(UsersRelationManager::class)
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ])
            ->filters([
                TernaryFilter::make('team_visible')
                    ->label('Auf der Homepage'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                SendPasswordResetLinkAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
