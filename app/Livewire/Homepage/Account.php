<?php

namespace App\Livewire\Homepage;

use App\Models\Contact;
use App\Models\Interest;
use App\Models\Kid;
use App\Services\Contacts\EmailChangeService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Parent area on the homepage: contact details, consent for course mails and the children with their interests.
 */
class Account extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $street = '';

    public string $zip = '';

    public string $city = '';

    public bool $wantsInterestMails = false;

    public string $newEmail = '';

    public bool $isEditingKid = false;

    public ?int $editingKidId = null;

    public string $kidName = '';

    public string $kidBirthdate = '';

    public bool $kidBirthdateIsEstimated = false;

    /**
     * @var list<int|string>
     */
    public array $kidInterestIds = [];

    public function mount(): void
    {
        $contact = $this->contact();

        $this->name = $contact->name;
        $this->phone = (string) $contact->phone;
        $this->street = (string) $contact->street;
        $this->zip = (string) $contact->zip;
        $this->city = (string) $contact->city;
        $this->wantsInterestMails = $contact->wantsInterestMails();
    }

    public function saveProfile(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'street' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:255'],
            'wantsInterestMails' => ['boolean'],
        ]);

        $contact = $this->contact();

        $contact->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?: null,
            'street' => $validated['street'] ?: null,
            'zip' => $validated['zip'] ?: null,
            'city' => $validated['city'] ?: null,
            'interest_mails_consent_at' => $validated['wantsInterestMails']
                ? ($contact->interest_mails_consent_at ?? now())
                : null,
        ]);

        session()->flash('profile_saved', true);
    }

    /**
     * Starts the two-step email change: the old address confirms first, then the new one.
     */
    public function requestEmailChange(EmailChangeService $emailChanges): void
    {
        $contact = $this->contact();

        $this->validate([
            'newEmail' => ['required', 'email:rfc', 'max:255', Rule::notIn([$contact->email])],
        ], [
            'newEmail.not_in' => 'Das ist bereits deine aktuelle Adresse.',
        ], ['newEmail' => 'neue E-Mail-Adresse']);

        $key = 'email-change:'.$contact->id;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('newEmail', 'Bitte warte ein paar Minuten, bevor du es erneut versuchst.');

            return;
        }

        RateLimiter::hit($key, 600);

        if ($emailChanges->isTaken($this->newEmail, $contact->id)) {
            $this->addError('newEmail', 'Diese Adresse kann nicht verwendet werden.');

            return;
        }

        $emailChanges->request($contact, $this->newEmail);
        $this->reset('newEmail');
    }

    public function cancelEmailChange(EmailChangeService $emailChanges): void
    {
        $emailChanges->cancelPending($this->contact());
    }

    public function createKid(): void
    {
        $this->resetKidForm();
        $this->isEditingKid = true;
    }

    public function editKid(int $kidId): void
    {
        $kid = $this->ownKid($kidId);

        $this->editingKidId = $kid->id;
        $this->kidName = $kid->name;
        $this->kidBirthdate = (string) $kid->exactBirthdate()?->toDateString();
        $this->kidBirthdateIsEstimated = $kid->birthdate_estimated;
        $this->kidInterestIds = $kid->interests->pluck('id')->all();
        $this->isEditingKid = true;
    }

    public function saveKid(): void
    {
        $validated = $this->validate([
            'kidName' => ['required', 'string', 'max:255'],
            'kidBirthdate' => ['required', 'date', 'before:today', 'after:'.now()->subYears(25)->toDateString()],
            'kidInterestIds' => ['array'],
            'kidInterestIds.*' => ['integer', Rule::exists('interests', 'id')->where('is_active', true)],
        ], attributes: [
            'kidName' => 'Name',
            'kidBirthdate' => 'Geburtsdatum',
        ]);

        DB::transaction(function () use ($validated): void {
            $kid = $this->editingKidId !== null ? $this->ownKid($this->editingKidId) : new Kid;

            $kid->fill([
                'name' => $validated['kidName'],
                'birthdate' => $validated['kidBirthdate'],
                'birthdate_estimated' => false,
            ])->save();

            $kid->interests()->sync(array_map('intval', $validated['kidInterestIds']));

            if ($this->editingKidId === null) {
                $this->contact()->kids()->attach($kid);
            }
        });

        $this->resetKidForm();
        unset($this->kids);
    }

    public function cancelKid(): void
    {
        $this->resetKidForm();
    }

    /**
     * Removes the child from this account. The record itself is only deleted if nobody else is linked
     * and it has no course history, so attendance records stay intact.
     */
    public function removeKid(int $kidId): void
    {
        $kid = $this->ownKid($kidId);

        DB::transaction(function () use ($kid): void {
            $this->contact()->kids()->detach($kid);

            if (! $kid->contacts()->exists() && ! $kid->courses()->exists()) {
                $kid->delete();
            }
        });

        if ($this->editingKidId === $kidId) {
            $this->resetKidForm();
        }

        unset($this->kids);
    }

    /**
     * @return Collection<int, Kid>
     */
    #[Computed]
    public function kids(): Collection
    {
        return $this->contact()->kids()->with('interests')->get();
    }

    /**
     * @return Collection<int, Interest>
     */
    #[Computed]
    public function interests(): Collection
    {
        return Interest::query()->active()->get();
    }

    public function render(): View
    {
        return view('livewire.homepage.account', [
            'contact' => $this->contact(),
            'pendingEmailChange' => $this->contact()->pendingEmailChange(),
        ]);
    }

    protected function contact(): Contact
    {
        /** @var Contact */
        return Auth::guard('contact')->user();
    }

    protected function ownKid(int $kidId): Kid
    {
        return $this->contact()->kids()->with('interests')->findOrFail($kidId);
    }

    protected function resetKidForm(): void
    {
        $this->reset(['isEditingKid', 'editingKidId', 'kidName', 'kidBirthdate', 'kidBirthdateIsEstimated', 'kidInterestIds']);
        $this->resetValidation();
    }
}
