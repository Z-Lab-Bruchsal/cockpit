<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Filament\Resources\Orders\Schemas\OrderProcessingFields;
use App\Models\Order;
use App\Models\Orderstatus;
use App\Models\User;
use App\Services\Orders\OrderProcessing;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * "Bestellt": people who place orders enter merchant, date, order number and price for the report,
 * everybody else just moves the request to the status "bestellt".
 */
class MarkOrderedAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'bestellt_single';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Bestellt')
            ->icon(Heroicon::ShoppingCart)
            ->authorize('update')
            ->visible(fn (Order $record): bool => $record->orderstatus?->name === 'erfasst')
            ->modalHeading(fn (Order $record): string => 'Bestellt: '.$record->name)
            ->modalSubmitActionLabel('Als bestellt erfassen')
            ->schema(fn (): ?array => OrderProcessing::canProcess(static::currentUser())
                ? OrderProcessingFields::make(OrderProcessingFields::orderUrl(...))
                : null)
            ->action(function (Order $record, array $data): void {
                $user = static::currentUser();

                if (OrderProcessing::canProcess($user)) {
                    OrderProcessing::markOrdered($record, $data, $user);

                    Notification::make()->success()->title('Als bestellt erfasst')->send();

                    return;
                }

                $record->orderstatus()->associate(Orderstatus::query()->where('name', 'bestellt')->firstOrFail());
                $record->save();
            });
    }

    protected static function currentUser(): ?User
    {
        return User::find(filament()->auth()->id());
    }
}
