<?php

namespace App\Services\Schools;

use App\Enums\ContactType;
use App\Exceptions\ReservationException;
use App\Mail\SchoolLoginLinkMail;
use App\Models\Contact;
use App\Services\Contacts\MagicLinkService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * School accounts (function address of the school) and the teacher sub-accounts they invite.
 * Both log in passwordless via magic link.
 */
class SchoolAccountService
{
    /**
     * Invitations stay valid longer than login links, teachers may not read their mail right away.
     */
    public const INVITATION_LIFETIME_MINUTES = 60 * 24 * 7;

    public function __construct(protected MagicLinkService $magicLinks) {}

    /**
     * Registers a school and sends the confirmation link to its function address. The school can only log in
     * after confirming. Known addresses just get a login link, so the form reveals nothing about existing accounts.
     *
     * @param  array{name: string, email: string, street: string, zip: string, city: string, phone?: ?string, contact_person?: ?string}  $data
     */
    public function register(array $data): void
    {
        $email = Str::lower(trim($data['email']));

        if (Contact::findByEmail($email) !== null) {
            $this->magicLinks->send($email, redirectTo: route('homepage.schools.account', absolute: false));

            return;
        }

        $school = Contact::query()->create([
            'type' => ContactType::School,
            'name' => trim($data['name']),
            'email' => $email,
            'street' => trim($data['street']),
            'zip' => trim($data['zip']),
            'city' => trim($data['city']),
            'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
            'contact_person' => filled($data['contact_person'] ?? null) ? trim($data['contact_person']) : null,
        ]);

        $this->sendLink($school, SchoolLoginLinkMail::REGISTRATION);
    }

    /**
     * Creates the teacher's sub-account and sends the invitation link.
     */
    public function inviteTeacher(Contact $school, string $name, string $email): Contact
    {
        $email = Str::lower(trim($email));
        $existing = Contact::findByEmail($email);

        if ($existing !== null && ! ($existing->isTeacher() && $existing->school_id === $school->id)) {
            throw new ReservationException('Diese Adresse wird schon für ein anderes Konto verwendet. Bitte nutzen Sie die dienstliche Adresse der Lehrkraft.');
        }

        $teacher = $existing ?? $school->teachers()->create([
            'type' => ContactType::Teacher,
            'name' => trim($name),
            'email' => $email,
        ]);

        $this->sendLink($teacher, SchoolLoginLinkMail::INVITATION, $school);

        return $teacher;
    }

    /**
     * Removes the sub-account. Its bookings stay with the school.
     */
    public function removeTeacher(Contact $school, Contact $teacher): void
    {
        if ($teacher->school_id !== $school->id || ! $teacher->isTeacher()) {
            return;
        }

        $teacher->registrations()->update(['contact_id' => null]);
        $teacher->loginTokens()->delete();
        $teacher->delete();
    }

    protected function sendLink(Contact $contact, string $kind, ?Contact $school = null): void
    {
        $plainToken = $this->magicLinks->issueToken(
            email: (string) $contact->email,
            contact: $contact,
            redirectTo: route('homepage.schools.account', absolute: false),
            lifetimeMinutes: $kind === SchoolLoginLinkMail::INVITATION ? self::INVITATION_LIFETIME_MINUTES : MagicLinkService::LIFETIME_MINUTES,
        );

        Mail::to($contact->email)->send(new SchoolLoginLinkMail(
            kind: $kind,
            url: route('homepage.login.confirm', $plainToken),
            contact: $contact,
            school: $school,
        ));
    }
}
