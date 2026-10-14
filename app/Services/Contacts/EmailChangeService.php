<?php

namespace App\Services\Contacts;

use App\Mail\ContactEmailChangeMail;
use App\Models\Contact;
use App\Models\ContactEmailChange;
use App\Models\ContactLoginToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Changing the email address from the homepage: the old address confirms first, then the new one.
 * The notice to the old address after the change is sent by the Contact model for every change.
 */
class EmailChangeService
{
    public const RESULT_OLD_CONFIRMED = 'old_confirmed';

    public const RESULT_COMPLETED = 'completed';

    public const RESULT_ADDRESS_TAKEN = 'address_taken';

    public function request(Contact $contact, string $newEmail): ContactEmailChange
    {
        $plainToken = Str::random(64);

        $change = DB::transaction(function () use ($contact, $newEmail, $plainToken): ContactEmailChange {
            $this->cancelPending($contact);

            return ContactEmailChange::query()->create([
                'contact_id' => $contact->id,
                'old_email' => $contact->email,
                'new_email' => Str::lower(trim($newEmail)),
                'old_token_hash' => ContactLoginToken::hash($plainToken),
                'expires_at' => now()->addHours(ContactEmailChange::LIFETIME_HOURS),
            ]);
        });

        Mail::to($change->old_email)->send(new ContactEmailChangeMail(
            step: ContactEmailChangeMail::STEP_CONFIRM_OLD,
            newEmail: $change->new_email,
            url: route('homepage.email-change.show', $plainToken),
            name: $contact->name,
        ));

        return $change;
    }

    public function cancelPending(Contact $contact): void
    {
        ContactEmailChange::query()
            ->where('contact_id', $contact->id)
            ->pending()
            ->update(['cancelled_at' => now()]);
    }

    /**
     * Pending change belonging to the token of either step, without using the token up.
     */
    public function find(string $plainToken): ?ContactEmailChange
    {
        $hash = ContactLoginToken::hash($plainToken);

        return ContactEmailChange::query()
            ->pending()
            ->where(fn ($query) => $query
                ->where(fn ($old) => $old->where('old_token_hash', $hash)->whereNull('old_confirmed_at'))
                ->orWhere('new_token_hash', $hash))
            ->first();
    }

    /**
     * Confirms the step belonging to the token. Returns one of the RESULT_* constants, or null for invalid tokens.
     */
    public function confirm(string $plainToken): ?string
    {
        $change = $this->find($plainToken);

        if ($change === null) {
            return null;
        }

        if ($this->isTaken($change->new_email, $change->contact_id)) {
            $change->update(['cancelled_at' => now()]);

            return self::RESULT_ADDRESS_TAKEN;
        }

        if ($change->isWaitingForOldAddress()) {
            $newToken = Str::random(64);

            $change->update([
                'old_confirmed_at' => now(),
                'new_token_hash' => ContactLoginToken::hash($newToken),
                'expires_at' => now()->addHours(ContactEmailChange::LIFETIME_HOURS),
            ]);

            Mail::to($change->new_email)->send(new ContactEmailChangeMail(
                step: ContactEmailChangeMail::STEP_CONFIRM_NEW,
                newEmail: $change->new_email,
                url: route('homepage.email-change.show', $newToken),
                name: $change->contact->name,
            ));

            return self::RESULT_OLD_CONFIRMED;
        }

        DB::transaction(function () use ($change): void {
            $change->update(['completed_at' => now()]);

            $contact = $change->contact;
            $contact->newEmailConfirmedByContact = true;
            $contact->update(['email' => $change->new_email]);
        });

        return self::RESULT_COMPLETED;
    }

    public function isTaken(string $email, ?int $exceptContactId = null): bool
    {
        return Contact::query()
            ->where('email', Str::lower(trim($email)))
            ->when($exceptContactId, fn ($query) => $query->whereKeyNot($exceptContactId))
            ->exists();
    }
}
