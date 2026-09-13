<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Benutzer')
                    ->options(function () {
                        if (! User::find(filament()->auth()->user()->id)->can('Worktimes:ViewForeign')) {
                            return User::where('id', filament()->auth()->user()->id)->pluck('name', 'id');
                        } else {
                            return User::all()->pluck('name', 'id');
                        }
                    })
                    ->searchable()
                    ->required(),
                DateTimePicker::make('start_at')
                    ->label('Von')
                    ->seconds(false)
                    ->required(),
                DateTimePicker::make('end_at')
                    ->label('Bis')
                    ->seconds(false)
                    ->after('start_at'),
                TextInput::make('note')
                    ->label('Notiz'),
            ]);
    }
}
