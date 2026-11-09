<?php

namespace App\Services\Orders;

use App\Models\Merchant;
use App\Models\Order;
use App\Models\Orderstatus;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Records that an order was placed: where, when, with which order number and at what cost.
 */
class OrderProcessing
{
    /**
     * Whether the user places orders and records them for accounting.
     */
    public static function canProcess(?User $user): bool
    {
        return (bool) $user?->can('Orders:Process');
    }

    /**
     * @param  array{merchant_id: int|string, orderdatetime: string|Carbon, order_number?: string|null, total_price?: float|string|null}  $data
     */
    public static function markOrdered(Order $order, array $data, User $processor): void
    {
        $merchant = Merchant::query()->findOrFail($data['merchant_id']);

        $order->update([
            'merchant_id' => $merchant->id,
            'orderdatetime' => Carbon::parse($data['orderdatetime']),
            'order_number' => filled($data['order_number'] ?? null) ? $data['order_number'] : null,
            'total_price' => static::parsePrice($data['total_price'] ?? null),
            'processed_by_user_id' => $processor->id,
            'orderstatus_id' => Orderstatus::query()->where('name', 'bestellt')->value('id') ?? $order->orderstatus_id,
        ]);

        // The first time a shop is used, its domain is remembered for the next orders.
        $merchant->rememberDomain(MerchantResolver::registrableDomain($order->url));
    }

    /**
     * "12,99", "12.99" or "1.234,50" → 12.99 / 1234.5; empty → null.
     */
    public static function parsePrice(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }

        $value = str_replace([' ', '€'], '', (string) $value);

        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }
}
