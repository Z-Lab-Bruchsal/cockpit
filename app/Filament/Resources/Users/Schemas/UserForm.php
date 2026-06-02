<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\Groups\Schemas\GroupForm;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                Select::make('roles')
                    ->relationship('roles', 'title')
                    ->label('Rollen')
                    ->preload()
                    ->multiple(),
                Select::make('groups')
                    ->relationship('groups', 'name')
                    ->label('Gruppen')
                    ->preload()
                    ->createOptionForm(function(Schema $schema) {
                        return GroupForm::configure($schema);
                    })
                    ->multiple(),
            ]);
    }
}
