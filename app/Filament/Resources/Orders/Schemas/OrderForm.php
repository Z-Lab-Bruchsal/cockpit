<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Resources\Orders\Actions\MarkOrderedAction;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Orderstatus;
use App\Models\User;
use App\Services\Orders\OrderProcessing;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Produktname')
                    ->required()
                    ->columnSpan(2),
                TextInput::make('count')
                    ->label('Anzahl')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('url')
                    ->label('URL')
                    ->suffixIcon(Heroicon::GlobeAlt)
                    ->url()
                    ->columnSpan(2),
                Select::make('user_id')
                    ->label('Bestellt von')
                    ->relationship('user', 'name')
                    ->required()
                    ->default(filament()->auth()->user()->id),
                Textarea::make('description')
                    ->label('Beschreibung')
                    ->placeholder('z. B. Farbe, Größe, wofür gebraucht')
                    ->rows(2)
                    ->maxLength(2000)
                    ->columnSpanFull(),
                Select::make('orderstatus_id')
                    ->label('Bestellstatus')
                    ->columnSpan(2)
                    ->relationship('orderstatus', 'name')
                    ->visibleOn(['edit'])
                    ->prefixActions([
                        MarkOrderedAction::make()
                            ->after(fn (Model $record) => redirect(OrderResource::getUrl('edit', ['record' => $record->id]))),
                        Action::make('angekommen_single')
                            ->authorize('update')
                            ->icon(Heroicon::BuildingOffice)->label('Angekommen')->action(function (Model $record) {
                                $orderstatusAngekommen = Orderstatus::where('name', 'angekommen')->first();
                                $record->orderstatus_id = $orderstatusAngekommen->id;
                                $record->save();
                                redirect(OrderResource::getUrl('edit', ['record' => $record->id]));
                            })->visible(function (Model $record) {
                                return $record->orderstatus->name == 'bestellt';
                            }),
                        Action::make('genommen_single')
                            ->authorize('update')
                            ->icon(Heroicon::Check)->label('Genommen')->action(function (Model $record) {
                                $orderstatusGenommen = Orderstatus::where('name', 'genommen')->first();
                                $record->orderstatus_id = $orderstatusGenommen->id;
                                $record->save();
                                redirect(OrderResource::getUrl('edit', ['record' => $record->id]));
                            })->visible(function (Model $record) {
                                return $record->orderstatus->name == 'angekommen';
                            }),
                        Action::make('url_oeffnen')->icon(Heroicon::Link)->label('URL öffnen')->url(function (Model $record) {
                            return Str::sanitizeUrl($record->url);
                        }, true),
                    ]),
                DateTimePicker::make('orderdatetime')
                    ->label('Bestellt am')
                    ->helperText('Wird beim Klick auf „Bestellt“ gesetzt.')
                    ->seconds(false)
                    ->visibleOn(['edit', 'view'])
                    ->hintAction(
                        Action::make('justOrdered')
                            ->label('Eben bestellt')
                            ->icon(Heroicon::Clock)
                            ->authorize('update')
                            ->action(function (Get $get, Set $set): void {
                                $set('orderdatetime', now()->format('Y-m-d H:i'));

                                $recordedStatusId = Orderstatus::query()->where('name', 'erfasst')->value('id');

                                if ((int) $get('orderstatus_id') === $recordedStatusId) {
                                    $set('orderstatus_id', Orderstatus::query()->where('name', 'bestellt')->value('id'));
                                }
                            }),
                    ),
                Section::make('Bestellung für die Buchhaltung')
                    ->description('Wo und zu welchem Preis bestellt wurde. Erscheint im Bestellbericht.')
                    ->schema(collect(OrderProcessingFields::make(OrderProcessingFields::orderUrl(...)))
                        ->reject(fn ($field): bool => $field->getName() === 'orderdatetime')
                        ->map(fn ($field) => $field->required(false))
                        ->all())
                    ->columns(3)
                    ->columnSpanFull()
                    ->visible(fn (string $operation): bool => $operation === 'edit' && static::canProcess()),
            ])->columns(3);
    }

    protected static function canProcess(): bool
    {
        return OrderProcessing::canProcess(User::find(filament()->auth()->id()));
    }
}
