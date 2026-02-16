<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Orderstatus;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('url')
                    ->suffixIcon(Heroicon::GlobeAlt)
                    ->url(),
                TextInput::make('count')
                    ->required()
                    ->numeric()
                    ->default(1),
                Select::make("orderstatus_id")->relationship("orderstatus", "name")->visibleOn(["edit"]),
                DateTimePicker::make('orderdatetime')->visibleOn(["edit", "view"]),
                Select::make('user_id')
                    ->relationship("user", "name")
                    ->required()
                    ->default(filament()->auth()->user()->id),
            ])->columns(1);
    }
}
