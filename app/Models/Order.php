<?php

namespace App\Models;

use App\Observers\OrderObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Orderstatus;

#[ObservedBy([OrderObserver::class])]
class Order extends Model
{
    protected $fillable = ["name", "url", "count", "orderstatus_id", "orderdatetime", "user_id"];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function orderstatus(): BelongsTo
    {
        return $this->belongsTo(Orderstatus::class);
    }


}