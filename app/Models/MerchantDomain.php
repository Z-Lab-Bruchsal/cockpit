<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Domain of a merchant without "www.", e.g. "reichelt.de". Subdomains match as well.
 */
#[Fillable(['merchant_id', 'domain'])]
class MerchantDomain extends Model
{
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
