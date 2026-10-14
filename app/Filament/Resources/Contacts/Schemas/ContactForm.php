<?php

namespace App\Filament\Resources\Contacts\Schemas;

use App\Enums\ContactType;
use App\Models\Contact;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Kontakt')
                    ->schema([
                        Select::make('type')
                            ->label('Art')
                            ->options(ContactType::class)
                            ->default(fn (): string => request()->query('type') === ContactType::School->value ? ContactType::School->value : ContactType::Parent->value)
                            ->required()
                            ->live(),
                        TextInput::make('name')
                            ->label(fn (Get $get): string => static::isSchool($get('type')) ? 'Name der Schule' : 'Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('contact_person')
                            ->label('Ansprechperson')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => static::isSchool($get('type'))),
                        TextInput::make('email')
                            ->label('E-Mail')
                            ->email()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('phone')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(50),
                    ])
                    ->columns(2),
                Section::make('Adresse')
                    ->schema([
                        TextInput::make('street')
                            ->label('Straße')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('zip')
                            ->label('PLZ')
                            ->maxLength(10),
                        TextInput::make('city')
                            ->label('Ort')
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make('Konto & Einwilligung')
                    ->schema([
                        TextEntry::make('email_verified_at')
                            ->label('E-Mail bestätigt')
                            ->dateTime()
                            ->placeholder('nein'),
                        TextEntry::make('last_login_at')
                            ->label('Letzte Anmeldung')
                            ->dateTime()
                            ->placeholder('noch nie'),
                        Toggle::make('wants_interest_mails')
                            ->label('Möchte Infos zu neuen Kursen')
                            ->helperText('Nur aktivieren, wenn eine Einwilligung vorliegt.')
                            ->afterStateHydrated(fn (Toggle $component, ?Contact $record) => $component->state((bool) $record?->interest_mails_consent_at)),
                        TextEntry::make('interest_mails_consent_at')
                            ->label('Einwilligung seit')
                            ->dateTime()
                            ->placeholder('—'),
                    ])
                    ->columns(2)
                    ->visibleOn('edit'),
                Section::make('Notizen')
                    ->schema([
                        Textarea::make('notes')
                            ->hiddenLabel()
                            ->rows(3),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected static function isSchool(mixed $type): bool
    {
        return ($type instanceof ContactType ? $type : ContactType::tryFrom((string) $type)) === ContactType::School;
    }
}
