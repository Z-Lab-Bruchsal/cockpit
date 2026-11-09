<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Merchant;
use App\Models\Order;
use App\Services\Orders\MerchantResolver;
use App\Services\Orders\OrderProcessing;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;

/**
 * Fields for recording a placed order: merchant (derived from the link, created on first use), date, order number, price.
 */
class OrderProcessingFields
{
    /**
     * @param  Closure(?Model): ?string  $url  Product link the merchant is derived from.
     * @return list<Select|DateTimePicker|TextInput>
     */
    public static function make(Closure $url, bool $withPrice = true): array
    {
        $fields = [
            Select::make('merchant_id')
                ->label('Händler')
                ->options(fn (): array => Merchant::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->required()
                ->default(fn (?Model $record): ?int => MerchantResolver::forUrl($url($record))?->id)
                ->helperText(fn (?Model $record, ?string $state): ?string => blank($state) && MerchantResolver::host($url($record)) !== null
                    ? 'Neuer Shop ('.MerchantResolver::host($url($record)).'): bitte einmal über „+“ anlegen oder auswählen – danach wird er automatisch erkannt.'
                    : null)
                ->createOptionForm([
                    TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->unique(Merchant::class, 'name', ignoreRecord: false)
                        ->default(fn (?Model $record): ?string => MerchantResolver::suggestName($url($record))),
                    TextInput::make('domain')
                        ->label('Domain')
                        ->helperText('Bestellungen von dieser Domain werden künftig diesem Händler zugeordnet.')
                        ->default(fn (?Model $record): ?string => MerchantResolver::registrableDomain($url($record)))
                        ->maxLength(255),
                ])
                ->createOptionUsing(function (array $data): int {
                    $merchant = Merchant::query()->create(['name' => $data['name']]);
                    $merchant->rememberDomain(filled($data['domain'] ?? null) ? mb_strtolower(trim($data['domain'])) : null);

                    return $merchant->id;
                }),
            DateTimePicker::make('orderdatetime')
                ->label('Bestellt am')
                ->seconds(false)
                ->default(now())
                ->required(),
            TextInput::make('order_number')
                ->label('Bestellnummer')
                ->maxLength(255),
        ];

        if ($withPrice) {
            $fields[] = static::price();
        }

        return $fields;
    }

    public static function price(string $name = 'total_price'): TextInput
    {
        return TextInput::make($name)
            ->label('Preis gesamt (brutto)')
            ->helperText('Inkl. Versand, z. B. 12,99')
            ->suffix('€')
            ->inputMode('decimal')
            ->regex('/^\d{1,3}(\.?\d{3})*(,\d{1,2})?$|^\d+(\.\d{1,2})?$/')
            ->formatStateUsing(fn (mixed $state): ?string => $state === null || $state === '' ? null : number_format((float) $state, 2, ',', ''))
            ->dehydrateStateUsing(fn (mixed $state): ?float => OrderProcessing::parsePrice($state))
            ->required();
    }

    public static function orderUrl(?Model $record): ?string
    {
        return $record instanceof Order ? $record->url : null;
    }
}
