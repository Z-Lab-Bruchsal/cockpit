<?php

namespace App\Livewire\Schools;

use App\Enums\RegistrationStatus;
use App\Exceptions\ReservationException;
use App\Models\Contact;
use App\Models\Registration;
use App\Services\Schools\SchoolAccountService;
use App\Services\Schools\SchoolBookingService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * School area on the homepage: school data and teachers (school account only) and the bookings.
 * The school account sees all bookings of the school, a teacher only their own.
 */
class SchoolAccount extends Component
{
    public string $name = '';

    public string $street = '';

    public string $zip = '';

    public string $city = '';

    public string $phone = '';

    public string $contactPerson = '';

    public string $teacherName = '';

    public string $teacherEmail = '';

    public function mount(): void
    {
        $contact = $this->contact();

        $this->name = $contact->name;
        $this->phone = (string) $contact->phone;

        if ($contact->isSchool()) {
            $this->street = (string) $contact->street;
            $this->zip = (string) $contact->zip;
            $this->city = (string) $contact->city;
            $this->contactPerson = (string) $contact->contact_person;
        }
    }

    /**
     * @return Collection<int, Registration>
     */
    #[Computed]
    public function registrations(): Collection
    {
        $contact = $this->contact();
        $query = $contact->isSchool()
            ? $contact->schoolRegistrations()
            : $contact->registrations()->whereNotNull('school_id');

        return $query
            ->whereNotIn('status', [RegistrationStatus::Held, RegistrationStatus::Expired])
            ->with(['courseInstance.course', 'courseInstance.dates', 'contact'])
            ->latest('id')
            ->get();
    }

    /**
     * @return Collection<int, Contact>
     */
    #[Computed]
    public function teachers(): Collection
    {
        $contact = $this->contact();

        return $contact->isSchool() ? $contact->teachers()->get() : new Collection;
    }

    public function saveProfile(): void
    {
        $contact = $this->contact();

        if ($contact->isSchool()) {
            $validated = $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'street' => ['required', 'string', 'max:255'],
                'zip' => ['required', 'string', 'max:10'],
                'city' => ['required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'contactPerson' => ['nullable', 'string', 'max:255'],
            ], attributes: ['name' => 'Name der Schule', 'street' => 'Straße', 'zip' => 'PLZ', 'city' => 'Ort', 'phone' => 'Telefon', 'contactPerson' => 'Ansprechpartner*in']);

            $contact->update([
                'name' => $validated['name'],
                'street' => $validated['street'],
                'zip' => $validated['zip'],
                'city' => $validated['city'],
                'phone' => $validated['phone'] ?: null,
                'contact_person' => $validated['contactPerson'] ?: null,
            ]);
        } else {
            $validated = $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
            ], attributes: ['name' => 'Name', 'phone' => 'Telefon']);

            $contact->update(['name' => $validated['name'], 'phone' => $validated['phone'] ?: null]);
        }

        session()->flash('profile_saved', true);
    }

    public function inviteTeacher(SchoolAccountService $accounts): void
    {
        $school = $this->contact();
        abort_unless($school->isSchool(), 403);

        $this->validate([
            'teacherName' => ['required', 'string', 'max:255'],
            'teacherEmail' => ['required', 'email:rfc', 'max:255'],
        ], attributes: ['teacherName' => 'Name', 'teacherEmail' => 'E-Mail-Adresse']);

        $key = 'teacher-invitation:'.$school->id;

        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->addError('teacherEmail', 'Zu viele Einladungen in kurzer Zeit. Bitte versuchen Sie es später noch einmal.');

            return;
        }

        RateLimiter::hit($key, 3600);

        try {
            $accounts->inviteTeacher($school, $this->teacherName, $this->teacherEmail);
        } catch (ReservationException $exception) {
            $this->addError('teacherEmail', $exception->getMessage());

            return;
        }

        session()->flash('teacher_invited', $this->teacherEmail);
        $this->reset('teacherName', 'teacherEmail');
        unset($this->teachers);
    }

    public function removeTeacher(int $teacherId, SchoolAccountService $accounts): void
    {
        $school = $this->contact();
        abort_unless($school->isSchool(), 403);

        $teacher = $school->teachers()->find($teacherId);

        if ($teacher !== null) {
            $accounts->removeTeacher($school, $teacher);
        }

        unset($this->teachers);
    }

    public function render(): View
    {
        return view('livewire.schools.school-account', [
            'contact' => $this->contact(),
            'schoolBookings' => app(SchoolBookingService::class),
        ]);
    }

    protected function contact(): Contact
    {
        /** @var Contact */
        return Auth::guard('contact')->user();
    }
}
