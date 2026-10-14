<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Collects held registrations of one visitor. Identified by a random token in a cookie; only its hash is stored.
 */
#[Fillable(['token_hash', 'contact_id'])]
class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Registrations still waiting in the cart, i.e. held and not expired.
     */
    public function heldRegistrations(): HasMany
    {
        return $this->registrations()
            ->where('status', RegistrationStatus::Held)
            ->where('expires_at', '>', now())
            ->orderBy('id');
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
