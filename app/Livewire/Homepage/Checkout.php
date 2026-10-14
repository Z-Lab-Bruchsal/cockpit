<?php

namespace App\Livewire\Homepage;

use App\Enums\EnrollmentStatus;
use App\Exceptions\ReservationException;
use App\Models\Contact;
use App\Models\Kid;
use App\Models\Registration;
use App\Services\Registrations\CartManager;
use App\Services\Registrations\CourseReservationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Cart page: shows held seats with countdown and collects contact and child details for the checkout.
 */
class Checkout extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $message = '';

    public bool $wantsInterestMails = false;

    public bool $acceptsPrivacy = false;

    /**
     * Child details per registration and seat: [registrationId => [[kid_id, name, birthdate], ...]].
     *
     * @var array<int, list<array{kid_id: int|string|null, name: string, birthdate: string}>>
     */
    public array $kids = [];

    public function mount(): void
    {
        if (($contact = $this->contact()) !== null) {
            $this->name = $contact->name;
            $this->email = (string) $contact->email;
            $this->phone = (string) $contact->phone;
            $this->wantsInterestMails = $contact->wantsInterestMails();
        }

        $this->syncKidSlots();
    }

    /**
     * @return Collection<int, Registration>
     */
    #[Computed]
    public function registrations(): Collection
    {
        $cart = app(CartManager::class)->current();

        if ($cart === null) {
            return new Collection;
        }

        return $cart->heldRegistrations()
            ->with(['courseInstance.course', 'courseInstance.dates', 'enrollments'])
            ->get();
    }

    /**
     * @return Collection<int, Kid>
     */
    #[Computed]
    public function ownKids(): Collection
    {
        return $this->contact()?->kids()->get() ?? new Collection;
    }

    public function remove(int $registrationId, CourseReservationService $reservations): void
    {
        $registration = $this->registrations->firstWhere('id', $registrationId);

        if ($registration !== null) {
            $reservations->removeFromCart($registration);
        }

        unset($this->registrations);
        $this->syncKidSlots();
    }

    public function checkout(CourseReservationService $reservations): mixed
    {
        unset($this->registrations);
        $this->syncKidSlots();

        if ($this->registrations->isEmpty()) {
            $this->addError('cart', 'Deine Reservierung ist abgelaufen. Bitte lege die Kurse erneut in den Warenkorb.');

            return null;
        }

        $this->validate($this->rules(), [
            'acceptsPrivacy.accepted' => 'Bitte stimme der Verarbeitung deiner Angaben zu.',
            'kids.*.*.name.required_without' => 'Bitte gib den Namen des Kindes an.',
            'kids.*.*.birthdate.required_without' => 'Bitte gib das Geburtsdatum an.',
        ], [
            'name' => 'Name',
            'email' => 'E-Mail-Adresse',
            'kids.*.*.birthdate' => 'Geburtsdatum',
        ]);

        if (! $this->validateKidsFitCourses()) {
            return null;
        }

        try {
            $result = $reservations->checkout(
                cart: app(CartManager::class)->current(),
                contactData: [
                    'email' => $this->email,
                    'name' => $this->name,
                    'phone' => $this->phone ?: null,
                    'wants_interest_mails' => $this->wantsInterestMails,
                    'message' => $this->message ?: null,
                ],
                kidsByRegistration: $this->kids,
                loggedInContact: $this->contact(),
            );
        } catch (ReservationException $exception) {
            $this->addError('cart', $exception->getMessage());
            unset($this->registrations);

            return null;
        }

        session()->flash('registration_confirmed_directly', $result->every(fn (Registration $registration): bool => $registration->confirmed_at !== null));
        session()->flash('registration_awaits_approval', $result->contains(fn (Registration $registration): bool => $registration->needs_approval));
        session()->flash('registration_email', $this->email);

        return $this->redirectRoute('homepage.cart.done');
    }

    public function render(): View
    {
        return view('livewire.homepage.checkout');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $ownKidIds = $this->ownKids->pluck('id')->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:2000'],
            'acceptsPrivacy' => ['accepted'],
            'wantsInterestMails' => ['boolean'],
            'kids.*.*.kid_id' => ['nullable', 'in:'.implode(',', $ownKidIds ?: [0])],
            'kids.*.*.name' => ['required_without:kids.*.*.kid_id', 'nullable', 'string', 'max:255'],
            'kids.*.*.birthdate' => ['required_without:kids.*.*.kid_id', 'nullable', 'date', 'before:today', 'after:'.now()->subYears(25)->toDateString()],
        ];
    }

    /**
     * Checks the age limits of each course and that no child takes two seats in the same course.
     */
    protected function validateKidsFitCourses(): bool
    {
        $isValid = true;
        $countsInCart = [];
        $reservations = app(CourseReservationService::class);

        foreach ($this->registrations as $registration) {
            $course = $registration->courseInstance->course;
            $firstDate = $registration->courseInstance->dates->first()?->starts_at;
            $seen = [];

            foreach ($this->kids[$registration->id] ?? [] as $index => $kid) {
                $key = "kids.{$registration->id}.{$index}";
                $ownKid = filled($kid['kid_id']) ? $this->ownKids->firstWhere('id', (int) $kid['kid_id']) : null;
                $identity = $ownKid !== null ? 'id:'.$ownKid->id : 'name:'.mb_strtolower(trim($kid['name']));

                if (in_array($identity, $seen, true)) {
                    $this->addError($key.($ownKid ? '.kid_id' : '.name'), 'Dieses Kind ist für diesen Kurs schon eingetragen.');
                    $isValid = false;
                }

                $seen[] = $identity;

                $limitKey = $ownKid !== null ? 'id:'.$ownKid->id : 'name:'.mb_strtolower(trim($kid['name'])).'|'.$kid['birthdate'];
                $countsInCart[$limitKey] = ($countsInCart[$limitKey] ?? 0) + 1;

                $limitMessage = $reservations->bookingLimitMessage($ownKid?->id, $ownKid?->name ?? $kid['name'], $ownKid?->exactBirthdate()?->toDateString() ?? $kid['birthdate'], $countsInCart[$limitKey]);

                if ($limitMessage !== null) {
                    $this->addError($key.($ownKid ? '.kid_id' : '.name'), $limitMessage);
                    $isValid = false;
                }

                // The exact birthdate is required for every registration, also for saved children without one.
                $birthdate = $ownKid?->exactBirthdate() ?? (filled($kid['birthdate']) ? Carbon::parse($kid['birthdate']) : null);

                if ($birthdate === null) {
                    $this->addError($key.'.birthdate', 'Bitte gib das Geburtsdatum an.');
                    $isValid = false;

                    continue;
                }

                $age = (int) floor($birthdate->diffInYears($firstDate ?? now()));

                if (($course->min_age !== null && $age < $course->min_age) || ($course->max_age !== null && $age > $course->max_age)) {
                    $this->addError($key.($ownKid?->exactBirthdate() ? '.kid_id' : '.birthdate'), "Der Kurs ist für {$course->ageRangeLabel()} gedacht.");
                    $isValid = false;
                }
            }
        }

        return $isValid;
    }

    /**
     * Keeps one child slot per active seat of every registration in the cart.
     */
    protected function syncKidSlots(): void
    {
        $slots = [];

        foreach ($this->registrations as $registration) {
            $seatCount = $registration->enrollments->whereIn('status', [EnrollmentStatus::Held, EnrollmentStatus::Waitlisted])->count();

            for ($index = 0; $index < $seatCount; $index++) {
                $slots[$registration->id][$index] = $this->kids[$registration->id][$index] ?? ['kid_id' => null, 'name' => '', 'birthdate' => ''];
            }
        }

        $this->kids = $slots;
    }

    protected function contact(): ?Contact
    {
        /** @var Contact|null */
        return Auth::guard('contact')->user();
    }
}
