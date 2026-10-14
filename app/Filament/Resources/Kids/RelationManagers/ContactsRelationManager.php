<?php

namespace App\Filament\Resources\Kids\RelationManagers;

use App\Filament\Resources\Contacts\ContactResource;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    protected static ?string $title = 'Eltern / Kontakte';

    protected static ?string $relatedResource = ContactResource::class;

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Name'),
                TextColumn::make('email')
                    ->label('E-Mail'),
                TextColumn::make('phone')
                    ->label('Telefon'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'email']),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make()
                    ->label('Zuordnung lösen'),
            ]);
    }
}
