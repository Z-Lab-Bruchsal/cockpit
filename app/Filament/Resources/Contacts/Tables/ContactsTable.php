<?php

namespace App\Filament\Resources\Contacts\Tables;

use App\Enums\ContactType;
use App\Filament\Resources\Contacts\Actions\SendLoginLinkAction;
use App\Models\Contact;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->description(fn (Contact $record): ?string => $record->contact_person)
                    ->searchable(['name', 'contact_person'])
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Art')
                    ->badge(),
                TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('phone')
                    ->label('Telefon')
                    ->toggleable(),
                TextColumn::make('kids.name')
                    ->label('Kinder')
                    ->badge()
                    ->separator(','),
                IconColumn::make('email_verified_at')
                    ->label('Konto')
                    ->boolean()
                    ->state(fn (Contact $record): bool => $record->hasVerifiedEmail())
                    ->tooltip(fn (Contact $record): string => $record->hasVerifiedEmail() ? 'E-Mail bestätigt' : 'Kein bestätigtes Konto'),
                IconColumn::make('interest_mails_consent_at')
                    ->label('Kursinfos')
                    ->boolean()
                    ->state(fn (Contact $record): bool => $record->wantsInterestMails()),
                TextColumn::make('city')
                    ->label('Ort')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Erstellt')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Art')
                    ->options(ContactType::class),
                TernaryFilter::make('email_verified_at')
                    ->label('Bestätigtes Konto')
                    ->nullable(),
                TernaryFilter::make('interest_mails_consent_at')
                    ->label('Möchte Kursinfos')
                    ->nullable(),
                SelectFilter::make('kid_interests')
                    ->label('Kinder interessieren sich für')
                    ->relationship('kids.interests', 'name')
                    ->multiple()
                    ->preload()
                    ->query(fn (Builder $query, array $data): Builder => filled($data['values'] ?? null)
                        ? $query->whereHas('kids.interests', fn (Builder $interests) => $interests->whereIn('interests.id', $data['values']))
                        : $query),
            ])
            ->recordActions([
                EditAction::make(),
                SendLoginLinkAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
