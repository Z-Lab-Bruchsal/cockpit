<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single-use magic link. Only the SHA-256 hash of the token is stored.
 * If no contact exists for the email yet, it is created when the link is used.
 */
#[Fillable(['contact_id', 'email', 'name', 'token_hash', 'redirect_to', 'expires_at', 'used_at'])]
class ContactLoginToken extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @param  Builder<ContactLoginToken>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('used_at')->where('expires_at', '>', now());
    }

    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
