<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pending change of a contact's email address: first confirmed via the old address, then via the new one.
 * Only SHA-256 hashes of the tokens are stored.
 */
#[Fillable(['contact_id', 'old_email', 'new_email', 'old_token_hash', 'new_token_hash', 'old_confirmed_at', 'completed_at', 'cancelled_at', 'expires_at'])]
class ContactEmailChange extends Model
{
    public const LIFETIME_HOURS = 24;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @param  Builder<ContactEmailChange>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('completed_at')->whereNull('cancelled_at')->where('expires_at', '>', now());
    }

    public function isWaitingForOldAddress(): bool
    {
        return $this->old_confirmed_at === null;
    }
}
