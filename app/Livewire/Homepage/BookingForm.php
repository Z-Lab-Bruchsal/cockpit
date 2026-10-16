<?php

namespace App\Livewire\Homepage;

use App\Enums\EnrollmentStatus;
use App\Exceptions\ReservationException;
use App\Http\Controllers\Homepage\RegistrationController;
use App\Models\Contact;
use App\Models\Enrollment;
use App\Models\Kid;
use App\Models\Registration;
use App\Rules\PhoneNumber;
use App\Services\Registrations\CourseReservationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Booking page of one course: shows the countdown of the held seats and collects contact and child details.
 */
class BookingForm extends Component
{
    #[Locked]
    public string $token = '';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $message = '';

    public bool $wantsInterestMails = false;

    public bool $acceptsPrivacy = false;

    /**
     * Child details per seat: [[kid_id, name, birthdate], ...].
     *
     * @var list<array{kid_id: int|string|null, name: string, birthdate: string}>
     */
    public array $kids = [];

    public function mount(string $token): void
    {
        $this->token = $token;

        if (($contact = $this->contact()) !== null) {
            $this->name = $contact->name;
            $this->email = (string) $contact->email;
            $this->phone = (string) $contact->phone;
            $this->wantsInterestMails = $contact->wantsInterestMails();
        }

        $this->syncKidSlots();
    }

    /**
     * The running booking, or null once its time has run out.
     */
    #[Computed]
    public function registration(): ?Registration
    {
        return Registration::query()
            ->heldWithToken($this->token)
            ->with(['courseInstance.course', 'courseInstance.dates', 'enrollments'])
            ->first();
    }

    /**
     * @return Collection<int, Kid>
     */
    #[Computed]
    public function ownKids(): Collection
    {
        return $this->contact()?->kids()->get() ?? new Collection;
    }

    public function checkout(CourseReservationService $reservations): mixed
    {
        unset($this->registration);

        if ($this->registration === null) {
            return $this->redirectRoute('homepage.booking.show', $this->token);
        }

        $this->syncKidSlots();

        $this->validate($this->rules(), [
            'phone.required' => 'Bitte gib eine Telefonnummer für Rückfragen an.',
            'acceptsPrivacy.accepted' => 'Bitte stimme der Verarbeitung deiner Angaben zu.',
            'kids.*.name.required_without' => 'Bitte gib den Namen des Kindes an.',
            'kids.*.birthdate.required_without' => 'Bitte gib das Geburtsdatum an.',
        ], [
            'name' => 'Name',
            'email' => 'E-Mail-Adresse',
            'phone' => 'Telefon',
            'kids.*.birthdate' => 'Geburtsdatum',
        ]);

        if (! $this->validateKidsFitCourse()) {
            return null;
        }

        try {
            $result = $reservations->checkout(
                registration: $this->registration,
                contactData: [
                    'email' => $this->email,
                    'name' => $this->name,
                    'phone' => $this->phone,
                    'wants_interest_mails' => $this->wantsInterestMails,
                    'message' => $this->message ?: null,
                ],
                kids: $this->kids,
                loggedInContact: $this->contact(),
            );
        } catch (ReservationException $exception) {
            $this->addError('booking', $exception->getMessage());
            unset($this->registration);

            return null;
        }

        session()->forget(RegistrationController::SESSION_KEY.'.'.$result->course_instance_id);
        session()->flash('registration_confirmed_directly', $result->confirmed_at !== null);
        session()->flash('registration_awaits_approval', $result->needs_approval);
        session()->flash('registration_email', $this->email);

        return $this->redirectRoute('homepage.booking.done');
    }

    public function render(): View
    {
        return view('livewire.homepage.booking-form');
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
            'phone' => ['required', 'string', 'max:50', new PhoneNumber],
            'message' => ['nullable', 'string', 'max:2000'],
            'acceptsPrivacy' => ['accepted'],
            'wantsInterestMails' => ['boolean'],
            'kids.*.kid_id' => ['nullable', 'in:'.implode(',', $ownKidIds ?: [0])],
            'kids.*.name' => ['required_without:kids.*.kid_id', 'nullable', 'string', 'max:255'],
            'kids.*.birthdate' => ['required_without:kids.*.kid_id', 'nullable', 'date', 'before:today', 'after:'.now()->subYears(25)->toDateString()],
        ];
    }

    /**
     * Checks the age limits, the booking limits and that no child is booked twice for this course.
     */
    protected function validateKidsFitCourse(): bool
    {
        $isValid = true;
        $reservations = app(CourseReservationService::class);
        $instance = $this->registration->courseInstance;
        $course = $instance->course;
        $firstDate = $instance->dates->first()?->starts_at;
        $seen = [];

        foreach ($this->kids as $index => $kid) {
            $key = "kids.{$index}";
            $ownKid = filled($kid['kid_id']) ? $this->ownKids->firstWhere('id', (int) $kid['kid_id']) : null;
            $errorKey = $key.($ownKid ? '.kid_id' : '.name');
            $identity = $ownKid !== null ? 'id:'.$ownKid->id : 'name:'.mb_strtolower(trim($kid['name']));

            if (in_array($identity, $seen, true)) {
                $this->addError($errorKey, 'Dieses Kind ist für diesen Kurs schon eingetragen.');
                $isValid = false;
            }

            $seen[] = $identity;

            // The exact birthdate is required for every registration, also for saved children without one.
            $birthdate = $ownKid?->exactBirthdate() ?? (filled($kid['birthdate']) ? Carbon::parse($kid['birthdate']) : null);

            if ($this->isAlreadyBooked($ownKid, $kid['name'], $birthdate)) {
                $this->addError($errorKey, 'Dieses Kind ist für diesen Kurs schon angemeldet.');
                $isValid = false;
            }

            $limitMessage = $reservations->bookingLimitMessage($ownKid?->id, $ownKid?->name ?? $kid['name'], $birthdate?->toDateString(), [$this->registration->id]);

            if ($limitMessage !== null) {
                $this->addError($errorKey, $limitMessage);
                $isValid = false;
            }

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

        return $isValid;
    }

    /**
     * Whether the child already has an active seat (or waitlist place) in this course from an earlier booking.
     */
    protected function isAlreadyBooked(?Kid $ownKid, string $name, ?Carbon $birthdate): bool
    {
        $name = mb_strtolower(trim($name));

        if ($ownKid === null && ($name === '' || $birthdate === null)) {
            return false;
        }

        return Enrollment::query()
            ->where('course_instance_id', $this->registration->course_instance_id)
            ->where('registration_id', '!=', $this->registration->id)
            ->whereNotIn('status', [EnrollmentStatus::Cancelled, EnrollmentStatus::Expired, EnrollmentStatus::Held])
            ->where(fn (Builder $query) => $ownKid !== null
                ? $query->where('kid_id', $ownKid->id)
                : $query->whereRaw('lower(kid_name) = ?', [$name])->whereDate('kid_birthdate', $birthdate->toDateString()))
            ->exists();
    }

    /**
     * Keeps one child slot per active seat of the booking.
     */
    protected function syncKidSlots(): void
    {
        $seatCount = $this->registration?->enrollments->whereIn('status', [EnrollmentStatus::Held, EnrollmentStatus::Waitlisted])->count() ?? 0;
        $slots = [];

        for ($index = 0; $index < $seatCount; $index++) {
            $slots[$index] = $this->kids[$index] ?? ['kid_id' => null, 'name' => '', 'birthdate' => ''];
        }

        $this->kids = $slots;
    }

    protected function contact(): ?Contact
    {
        /** @var Contact|null */
        return Auth::guard('contact')->user();
    }
}
