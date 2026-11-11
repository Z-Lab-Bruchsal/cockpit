<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Groups\Schemas\GroupForm;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

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
                    ->revealable()
                    ->required()
                    ->rule(Password::default())
                    ->helperText('Mind. 10 Zeichen, Groß- und Kleinbuchstaben und eine Zahl.')
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
                TextInput::make('weekly_hours')
                    ->label('Vereinbarte Wochenstunden')
                    ->helperText('Die Stundenauswertung markiert Wochen, in denen mehr gearbeitet wurde. Leer lassen, wenn keine Wochenstunden vereinbart sind.')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(80)
                    ->step(0.25)
                    ->suffix('h')
                    ->visible(fn (): bool => (bool) User::find(filament()->auth()->id())?->can('Worktimes:SupervisorReport')),
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
