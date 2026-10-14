<?php

namespace App\Services\Contacts;

use App\Enums\ContactType;
use App\Mail\ContactMagicLinkMail;
use App\Models\Contact;
use App\Models\ContactLoginToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Passwordless login and registration for parents and schools.
 */
class MagicLinkService
{
    public const LIFETIME_MINUTES = 30;

    /**
     * Sends a login link. For unknown addresses the contact is only created once the link is used,
     * so requesting a link never creates records for addresses nobody controls.
     */
    public function send(string $email, ?string $name = null, ?string $redirectTo = null): void
    {
        $email = Str::lower(trim($email));
        $contact = Contact::findByEmail($email);
        $plainToken = $this->issueToken($email, $contact, $name, $redirectTo);

        Mail::to($email)->send(new ContactMagicLinkMail(
            url: route('homepage.login.confirm', $plainToken),
            isNewContact: $contact === null,
            name: $contact?->name ?? $name,
        ));
    }

    /**
     * Stores a new single-use token and returns it in plain text for the link.
     * Tokens for an existing contact log in exactly that contact, e.g. a school or an invited teacher.
     */
    public function issueToken(string $email, ?Contact $contact = null, ?string $name = null, ?string $redirectTo = null, int $lifetimeMinutes = self::LIFETIME_MINUTES): string
    {
        $plainToken = Str::random(64);

        ContactLoginToken::query()->create([
            'contact_id' => $contact?->id,
            'email' => Str::lower(trim($email)),
            'name' => filled($name) ? Str::limit(trim($name), 255, '') : null,
            'token_hash' => ContactLoginToken::hash($plainToken),
            'redirect_to' => $this->safeRedirect($redirectTo),
            'expires_at' => now()->addMinutes($lifetimeMinutes),
        ]);

        return $plainToken;
    }

    /**
     * Valid token for the confirmation page, without using it up.
     */
    public function find(string $plainToken): ?ContactLoginToken
    {
        return ContactLoginToken::query()
            ->usable()
            ->where('token_hash', ContactLoginToken::hash($plainToken))
            ->first();
    }

    /**
     * Uses the token up and returns the contact to log in, creating it for first-time registrations.
     */
    public function consume(string $plainToken): ?Contact
    {
        return DB::transaction(function () use ($plainToken): ?Contact {
            $token = ContactLoginToken::query()
                ->usable()
                ->where('token_hash', ContactLoginToken::hash($plainToken))
                ->lockForUpdate()
                ->first();

            if ($token === null) {
                return null;
            }

            $token->update(['used_at' => now()]);

            $contact = $token->contact ?? Contact::findByEmail($token->email) ?? Contact::query()->create([
                'type' => ContactType::Parent,
                'email' => $token->email,
                'name' => $token->name ?: Str::before($token->email, '@'),
            ]);

            $contact->forceFill([
                'email_verified_at' => $contact->email_verified_at ?? now(),
                'last_login_at' => now(),
            ])->save();

            return $contact;
        });
    }

    public function redirectFor(string $plainToken): ?string
    {
        return ContactLoginToken::query()
            ->where('token_hash', ContactLoginToken::hash($plainToken))
            ->value('redirect_to');
    }

    /**
     * Only relative paths on this site are allowed as redirect targets.
     */
    protected function safeRedirect(?string $redirectTo): ?string
    {
        if (blank($redirectTo) || ! str_starts_with($redirectTo, '/') || str_starts_with($redirectTo, '//') || str_contains($redirectTo, '\\')) {
            return null;
        }

        return Str::limit($redirectTo, 255, '');
    }
}
