<?php

namespace App\Livewire\Schools;

use App\Enums\SchoolType;
use App\Enums\SchoolVenue;
use App\Exceptions\ReservationException;
use App\Http\Controllers\Homepage\RegistrationController;
use App\Models\Contact;
use App\Models\Registration;
use App\Rules\PhoneNumber;
use App\Services\Schools\SchoolBookingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Booking page of one school class slot: countdown of the held slot and the details of the class.
 */
class SchoolBookingForm extends Component
{
    #[Locked]
    public string $token = '';

    public bool $acceptsPrivacy = false;

    public string $className = '';

    public string $schoolType = '';

    public string $grade = '';

    public string $students = '';

    public string $teacherName = '';

    public string $teacherPhone = '';

    public string $topic = '';

    public string $venue = '';

    public function mount(string $token, SchoolBookingService $schoolBookings): void
    {
        $this->token = $token;

        $registration = $this->registration;
        $contact = $this->contact();
        $previous = $registration?->school_class_data ?? [];
        $lastClass = ($school = $contact?->schoolAccount()) !== null ? $schoolBookings->lastClassDetails($school) : ['school_type' => null, 'grade' => null];
        $allowedTypes = $registration?->courseInstance->course->allowedSchoolTypes() ?? [];

        $this->className = (string) ($previous['class_name'] ?? '');
        $this->grade = (string) ($previous['grade'] ?? '');
        $this->students = (string) ($previous['students'] ?? '');
        $this->teacherName = (string) ($previous['teacher_name'] ?? ($contact?->isTeacher() ? $contact->name : ''));
        $this->teacherPhone = (string) ($previous['teacher_phone'] ?? $contact?->phone ?? '');
        $this->topic = (string) ($previous['topic'] ?? '');
        $this->venue = (string) ($registration?->venue?->value ?? '');

        // Most schools are of one type: preselect the last one, or the only one the course allows.
        $schoolType = SchoolType::tryFrom((string) ($previous['school_type'] ?? $lastClass['school_type'] ?? ''));
        $this->schoolType = match (true) {
            $schoolType !== null && in_array($schoolType, $allowedTypes, true) => $schoolType->value,
            count($allowedTypes) === 1 => $allowedTypes[0]->value,
            default => '',
        };
    }

    /**
     * The running booking, or null once its time has run out.
     */
    #[Computed]
    public function registration(): ?Registration
    {
        return Registration::query()
            ->heldWithToken($this->token)
            ->where('contact_id', $this->contact()?->id)
            ->with(['courseInstance.course', 'courseInstance.dates', 'replacesRegistration.courseInstance.dates'])
            ->first();
    }

    public function checkout(SchoolBookingService $schoolBookings): mixed
    {
        unset($this->registration);

        if ($this->registration === null) {
            return $this->redirectRoute('homepage.schools.booking.show', $this->token);
        }

        $this->validate($this->rules(), [
            'teacherPhone.required' => 'Bitte geben Sie eine Telefonnummer für Rückfragen an.',
            'acceptsPrivacy.accepted' => 'Bitte stimmen Sie der Verarbeitung der Angaben zu.',
            'venue.required' => 'Bitte wählen Sie den Ort.',
            'schoolType.required' => 'Bitte wählen Sie die Schulart.',
            'schoolType.in' => 'Dieser Kurs ist nicht für diese Schulart gedacht.',
            'grade.required' => 'Bitte wählen Sie die Klassenstufe.',
            'grade.in' => 'Dieser Kurs ist nicht für diese Klassenstufe gedacht.',
            'students.max' => 'Für diesen Kurs sind höchstens :max Kinder möglich.',
        ], [
            'className' => 'Klasse',
            'students' => 'Anzahl Kinder',
            'teacherName' => 'Lehrkraft',
            'teacherPhone' => 'Telefon',
            'topic' => 'Thema / Anmerkung',
        ]);

        try {
            $registration = $schoolBookings->checkout($this->registration, [
                'class_name' => $this->className,
                'grade' => $this->grade,
                'school_type' => $this->schoolType,
                'students' => $this->students,
                'teacher_name' => $this->teacherName,
                'teacher_phone' => $this->teacherPhone,
                'topic' => $this->topic,
                'venue' => $this->venue,
            ], $this->contact());
        } catch (ReservationException $exception) {
            $this->addError('booking', $exception->getMessage());
            unset($this->registration);

            return null;
        }

        session()->forget(RegistrationController::SESSION_KEY.'.'.$registration->course_instance_id);

        return $this->redirectRoute('homepage.schools.booking.done');
    }

    public function render(): View
    {
        return view('livewire.schools.school-booking-form', [
            'contact' => $this->contact(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $instance = $this->registration->courseInstance;
        $course = $instance->course;

        $rules = [
            'acceptsPrivacy' => ['accepted'],
            'className' => ['required', 'string', 'max:50'],
            'schoolType' => ['required', Rule::in(array_map(fn (SchoolType $type): string => $type->value, $course->allowedSchoolTypes()))],
            'grade' => ['required', 'integer', Rule::in($course->allowedGrades())],
            'students' => ['required', 'integer', 'min:1', 'max:'.($instance->maxClassSize() ?? 200)],
            'teacherName' => ['required', 'string', 'max:255'],
            'teacherPhone' => ['required', 'string', 'max:50', new PhoneNumber],
            'topic' => ['nullable', 'string', 'max:2000'],
        ];

        if (count($instance->allowedVenues()) > 1) {
            $rules['venue'] = ['required', Rule::enum(SchoolVenue::class)];
        }

        return $rules;
    }

    protected function contact(): ?Contact
    {
        /** @var Contact|null */
        return Auth::guard('contact')->user();
    }
}
