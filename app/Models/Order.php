<?php

namespace App\Models;

use App\Observers\OrderObserver;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([OrderObserver::class])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = ['name', 'url', 'count', 'orderstatus_id', 'orderdatetime', 'user_id', 'merchant_id', 'order_number', 'total_price', 'processed_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orderdatetime' => 'datetime',
            'total_price' => 'decimal:2',
            'count' => 'integer',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    /**
     * Orders waiting to be placed (status "erfasst").
     *
     * @param  Builder<Order>  $query
     */
    public function scopeToProcess(Builder $query): void
    {
        $query->whereHas('orderstatus', fn (Builder $status) => $status->where('name', 'erfasst'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderstatus(): BelongsTo
    {
        return $this->belongsTo(Orderstatus::class);
    }
}
