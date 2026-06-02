<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Orderstatus;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')->inlineLabel()
                    ->required()
                    ->columnSpan(1),
                TextInput::make('count')
                    ->label('Anzahl')->inlineLabel()
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('url')
                    ->label('URL')->inlineLabel()
                    ->suffixIcon(Heroicon::GlobeAlt)
                    ->url()
                    ->columnSpan(1),
                Select::make('user_id')
                    ->label('Bestellt von')->inlineLabel()
                    ->relationship('user', 'name')
                    ->required()
                    ->default(filament()->auth()->user()->id),
                Select::make('orderstatus_id')
                    ->label('Bestellstatus')->inlineLabel()
                    ->relationship('orderstatus', 'name')
                    ->visibleOn(['edit'])
                    ->prefixActions([
                        Action::make('bestellt_single')
                            ->icon(Heroicon::ShoppingCart)->label('Bestellt')->action(function (Model $record) {
                                $orderstatusBestellt = Orderstatus::where('name', 'bestellt')->first();
                                $record->orderstatus_id = $orderstatusBestellt->id;
                                $record->save();
                                redirect(OrderResource::getUrl('edit', ['record' => $record->id]));
                            })->visible(function (Model $record) {
                                return $record->orderstatus->name == 'erfasst';
                            }),
                        Action::make('angekommen_single')
                            ->icon(Heroicon::BuildingOffice)->label('Angekommen')->action(function (Model $record) {
                                $orderstatusAngekommen = Orderstatus::where('name', 'angekommen')->first();
                                $record->orderstatus_id = $orderstatusAngekommen->id;
                                $record->save();
                                redirect(OrderResource::getUrl('edit', ['record' => $record->id]));
                            })->visible(function (Model $record) {
                                return $record->orderstatus->name == 'bestellt';
                            }),
                        Action::make('genommen_single')
                            ->icon(Heroicon::Check)->label('Genommen')->action(function (Model $record) {
                                $orderstatusGenommen = Orderstatus::where('name', 'genommen')->first();
                                $record->orderstatus_id = $orderstatusGenommen->id;
                                $record->save();
                                redirect(OrderResource::getUrl('edit', ['record' => $record->id]));
                            })->visible(function (Model $record) {
                                return $record->orderstatus->name == 'angekommen';
                            }),
                        Action::make('url_oeffnen')->icon(Heroicon::Link)->label('URL öffnen')->url(function (Model $record) {
                            return $record->url;
                        }, true),
                    ]),
                DateTimePicker::make('orderdatetime')
                    ->label('Bestellzeitpunkt')->inlineLabel()
                    ->visibleOn(['edit', 'view'])
                    ->disabled(),
            ])->columns(1);
    }
}
