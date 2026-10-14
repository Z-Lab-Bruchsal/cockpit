<?php

namespace App\Filament\Resources\Contacts\RelationManagers;

use App\Filament\Resources\Kids\Schemas\KidForm;
use App\Models\Kid;
use Filament\Actions\AttachAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KidsRelationManager extends RelationManager
{
    protected static string $relationship = 'kids';

    protected static ?string $title = 'Kinder';

    protected static ?string $modelLabel = 'Kind';

    protected static ?string $pluralModelLabel = 'Kinder';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(KidForm::fields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Name'),
                TextColumn::make('age')
                    ->label('Alter')
                    ->state(fn (Kid $record): ?int => $record->age())
                    ->suffix(' Jahre')
                    ->placeholder('—'),
                TextColumn::make('interests.name')
                    ->label('Interessen')
                    ->badge()
                    ->separator(','),
            ])
            ->headerActions([
                CreateAction::make(),
                AttachAction::make()
                    ->label('Vorhandenes Kind zuordnen')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name']),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make()
                    ->label('Zuordnung lösen'),
            ]);
    }
}
