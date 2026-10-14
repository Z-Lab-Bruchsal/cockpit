<?php

namespace App\Livewire\Schools;

use App\Enums\SchoolVenue;
use App\Exceptions\ReservationException;
use App\Models\Contact;
use App\Models\Registration;
use App\Services\Registrations\CartManager;
use App\Services\Registrations\CourseReservationService;
use App\Services\Schools\SchoolBookingService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Cart for schools: held slots with countdown and the class details per slot.
 */
class SchoolCheckout extends Component
{
    public bool $acceptsPrivacy = false;

    /**
     * Class details per registration: [registrationId => [class_name, students, teacher_name, teacher_phone, topic, venue]].
     *
     * @var array<int, array{class_name: string, students: int|string, teacher_name: string, teacher_phone: string, topic: string, venue: string}>
     */
    public array $classes = [];

    public function mount(): void
    {
        $this->syncClassSlots();
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
            ->with(['courseInstance.course', 'courseInstance.dates', 'replacesRegistration.courseInstance.dates'])
            ->get();
    }

    public function remove(int $registrationId, CourseReservationService $reservations): void
    {
        $registration = $this->registrations->firstWhere('id', $registrationId);

        if ($registration !== null) {
            $reservations->removeFromCart($registration);
        }

        unset($this->registrations);
        $this->syncClassSlots();
    }

    public function checkout(SchoolBookingService $schoolBookings): mixed
    {
        unset($this->registrations);
        $this->syncClassSlots();

        if ($this->registrations->isEmpty()) {
            $this->addError('cart', 'Ihre Reservierung ist abgelaufen. Bitte legen Sie die Termine erneut in den Warenkorb.');

            return null;
        }

        $this->validate($this->rules(), [
            'acceptsPrivacy.accepted' => 'Bitte stimmen Sie der Verarbeitung der Angaben zu.',
            'classes.*.venue.required' => 'Bitte wählen Sie den Ort.',
        ], [
            'classes.*.class_name' => 'Klasse',
            'classes.*.students' => 'Anzahl Schüler*innen',
            'classes.*.teacher_name' => 'Lehrkraft',
            'classes.*.teacher_phone' => 'Telefon',
            'classes.*.topic' => 'Thema / Anmerkung',
        ]);

        try {
            $schoolBookings->checkout(app(CartManager::class)->current(), $this->classes, $this->contact());
        } catch (ReservationException $exception) {
            $this->addError('cart', $exception->getMessage());
            unset($this->registrations);

            return null;
        }

        return $this->redirectRoute('homepage.schools.cart.done');
    }

    public function render(): View
    {
        return view('livewire.schools.school-checkout', [
            'contact' => $this->contact(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [
            'acceptsPrivacy' => ['accepted'],
            'classes.*.class_name' => ['required', 'string', 'max:50'],
            'classes.*.students' => ['required', 'integer', 'min:1', 'max:200'],
            'classes.*.teacher_name' => ['required', 'string', 'max:255'],
            'classes.*.teacher_phone' => ['nullable', 'string', 'max:50'],
            'classes.*.topic' => ['nullable', 'string', 'max:2000'],
        ];

        foreach ($this->registrations as $registration) {
            $venues = $registration->courseInstance->allowedVenues();

            if (count($venues) > 1) {
                $rules["classes.{$registration->id}.venue"] = ['required', Rule::enum(SchoolVenue::class)];
            }
        }

        return $rules;
    }

    /**
     * Keeps one class entry per slot, prefilled from a moved booking or the logged-in teacher.
     */
    protected function syncClassSlots(): void
    {
        $contact = $this->contact();
        $slots = [];

        foreach ($this->registrations as $registration) {
            $previous = $registration->school_class_data ?? [];

            $slots[$registration->id] = $this->classes[$registration->id] ?? [
                'class_name' => (string) ($previous['class_name'] ?? ''),
                'students' => (string) ($previous['students'] ?? ''),
                'teacher_name' => (string) ($previous['teacher_name'] ?? ($contact?->isTeacher() ? $contact->name : '')),
                'teacher_phone' => (string) ($previous['teacher_phone'] ?? $contact?->phone ?? ''),
                'topic' => (string) ($previous['topic'] ?? ''),
                'venue' => (string) ($registration->venue?->value ?? ''),
            ];
        }

        $this->classes = $slots;
    }

    protected function contact(): ?Contact
    {
        /** @var Contact|null */
        return Auth::guard('contact')->user();
    }
}
