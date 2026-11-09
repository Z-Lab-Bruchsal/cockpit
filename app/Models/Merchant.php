<?php

namespace App\Models;

use Database\Factories\MerchantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shop an order is placed at, e.g. Amazon or Reichelt. Recognized by the domains of the product links.
 */
#[Fillable(['name'])]
class Merchant extends Model
{
    /** @use HasFactory<MerchantFactory> */
    use HasFactory;

    public function domains(): HasMany
    {
        return $this->hasMany(MerchantDomain::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Remembers the domain, so that the next order from it is assigned automatically.
     */
    public function rememberDomain(?string $domain): void
    {
        if (blank($domain) || MerchantDomain::query()->where('domain', $domain)->exists()) {
            return;
        }

        $this->domains()->create(['domain' => $domain]);
    }
}
