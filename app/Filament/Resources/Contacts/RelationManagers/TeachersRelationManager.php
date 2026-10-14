<?php

namespace App\Filament\Resources\Contacts\RelationManagers;

use App\Filament\Resources\Contacts\ContactResource;
use App\Models\Contact;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Teacher sub-accounts of a school. Schools invite them on the homepage.
 */
class TeachersRelationManager extends RelationManager
{
    protected static string $relationship = 'teachers';

    protected static ?string $title = 'Lehrkräfte';

    protected static ?string $modelLabel = 'Lehrkraft';

    protected static ?string $pluralModelLabel = 'Lehrkräfte';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Contact && $ownerRecord->isSchool() && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Name'),
                TextColumn::make('email')
                    ->label('E-Mail')
                    ->copyable(),
                TextColumn::make('email_verified_at')
                    ->label('Zugang aktiviert')
                    ->dateTime()
                    ->placeholder('eingeladen'),
                TextColumn::make('last_login_at')
                    ->label('Letzte Anmeldung')
                    ->dateTime()
                    ->placeholder('noch nie'),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Öffnen')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Contact $record): string => ContactResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
