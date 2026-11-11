<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Groups\Schemas\GroupForm;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required()
                    ->visibleOn(['create']),
                Section::make('Adresse')
                    ->schema([
                        TextInput::make('street')
                            ->label('Straße'),
                        TextInput::make('zip')
                            ->label('PLZ'),
                        TextInput::make('city')
                            ->label('Ort'),
                    ])
                    ->columns(3),
                Select::make('groups')
                    ->relationship('groups', 'name')
                    ->label('Gruppen')
                    ->preload()
                    ->createOptionForm(function (Schema $schema) {
                        return GroupForm::configure($schema);
                    })
                    ->multiple(),
                Select::make('roles')
                    ->relationship('roles', 'name')
                    ->label('Rollen')
                    ->multiple()
                    ->preload()
                    ->searchable(),
            ]);
    }
}
