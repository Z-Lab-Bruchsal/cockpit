<?php

namespace App\Http\Controllers\Homepage;

use App\Enums\CourseAudience;
use App\Enums\CourseInstanceStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SchoolSlotState;
use App\Exceptions\ReservationException;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\CourseDate;
use App\Models\CourseInstance;
use App\Models\Registration;
use App\Services\Registrations\CartManager;
use App\Services\Schools\SchoolAccountService;
use App\Services\Schools\SchoolBookingService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Area for schools and teachers on the homepage: registration, free slots (calendar and list),
 * cart, and managing booked visits.
 */
class SchoolController extends Controller
{
    /**
     * Longest period a single event request may cover, to keep responses small.
     */
    protected const MAX_RANGE_DAYS = 100;

    public function __construct(
        protected SchoolBookingService $schoolBookings,
        protected CartManager $carts,
    ) {}

    public function index(Request $request): View
    {
        return view('homepage.schools.index', [
            'contact' => $this->contact($request),
        ]);
    }

    public function showRegistration(Request $request): View|RedirectResponse
    {
        if ($this->contact($request)?->canBookForSchool()) {
            return redirect()->route('homepage.schools.account');
        }

        return view('homepage.schools.register');
    }

    public function register(Request $request, SchoolAccountService $accounts): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'zip' => ['required', 'string', 'max:10'],
            'city' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'accepts_privacy' => ['accepted'],
            // Honeypot: real visitors never fill this hidden field.
            'website' => ['prohibited'],
        ], [
            'accepts_privacy.accepted' => 'Bitte stimmen Sie der Verarbeitung der Angaben zu.',
        ]);

        $accounts->register($validated);

        return redirect()->route('homepage.schools.register')->with('school_registration_sent', $validated['email']);
    }

    public function calendar(): View
    {
        return view('homepage.schools.calendar');
    }

    public function events(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ]);

        $start = Carbon::parse($validated['start']);
        $end = Carbon::parse($validated['end']);

        if ($start->diffInDays($end) > self::MAX_RANGE_DAYS) {
            $end = $start->copy()->addDays(self::MAX_RANGE_DAYS);
        }

        $dates = CourseDate::query()
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->whereHas('courseInstance', fn (Builder $instances) => $instances
                ->where('status', CourseInstanceStatus::Published)
                ->whereHas('course', fn (Builder $courses) => $courses->published()->where('audience', CourseAudience::SchoolClass)))
            ->with(['courseInstance.course', 'courseInstance.firstDate'])
            ->orderBy('starts_at')
            ->get();

        return response()->json($dates->map(function (CourseDate $date): array {
            $instance = $date->courseInstance;
            $state = SchoolSlotState::for($instance);

            return [
                'id' => $date->id,
                'title' => $instance->displayTitle(),
                'start' => $date->starts_at->toIso8601String(),
                'end' => $date->ends_at->toIso8601String(),
                'url' => route('homepage.schools.slots').'#termin-'.$instance->id,
                'backgroundColor' => $state->hexColor(),
                'borderColor' => $state->hexColor(),
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'availability' => $state->getLabel(),
                    'location' => $instance->course->effectiveSchoolFormat()->getLabel(),
                ],
            ];
        })->values());
    }

    public function slots(Request $request): View
    {
        /** @var Collection<int, CourseInstance> $instances */
        $instances = CourseInstance::query()
            ->published()
            ->upcoming()
            ->whereHas('course', fn (Builder $courses) => $courses->published()->where('audience', CourseAudience::SchoolClass))
            ->with(['course', 'dates'])
            ->orderByFirstDate()
            ->get();

        return view('homepage.schools.slots', [
            'courses' => $instances->groupBy('course_id'),
            'contact' => $this->contact($request),
        ]);
    }

    public function addToCart(Request $request, CourseInstance $instance): RedirectResponse
    {
        abort_unless($instance->course?->is_published && $instance->course->isForSchoolClasses(), 404);

        try {
            $this->schoolBookings->hold($instance, $this->carts->currentOrCreate(), $this->contact($request));
        } catch (ReservationException $exception) {
            return redirect()
                ->to(route('homepage.schools.slots').'#termin-'.$instance->id)
                ->with('school_error', $exception->getMessage());
        }

        return redirect()->route('homepage.schools.cart')->with('status', 'Der Termin ist für Sie reserviert. Bitte ergänzen Sie die Angaben zur Klasse.');
    }

    public function cart(): View
    {
        return view('homepage.schools.cart');
    }

    public function done(): View
    {
        return view('homepage.schools.done');
    }

    public function account(): View
    {
        return view('homepage.schools.account');
    }

    public function cancel(Request $request, Registration $registration): RedirectResponse
    {
        abort_unless($registration->canBeManagedBy($this->contact($request)), 404);

        try {
            $this->schoolBookings->cancelBySchool($registration);
        } catch (ReservationException $exception) {
            return redirect()->route('homepage.schools.account')->with('school_error', $exception->getMessage());
        }

        return redirect()->route('homepage.schools.account')->with('status', 'Der Termin ist storniert. Wir haben die Kursleitung informiert.');
    }

    public function showReschedule(Request $request, Registration $registration): View|RedirectResponse
    {
        abort_unless($registration->canBeManagedBy($this->contact($request)), 404);

        if (! $this->schoolBookings->canBeChangedBySchool($registration)) {
            return redirect()->route('homepage.schools.account')->with('school_error', 'Dieser Termin kann nicht mehr online verschoben werden. Bitte rufen Sie uns an.');
        }

        $registration->load(['courseInstance.course', 'courseInstance.dates']);

        return view('homepage.schools.reschedule', [
            'registration' => $registration,
            'options' => $this->schoolBookings->rescheduleOptions($registration),
        ]);
    }

    /**
     * Holds the new slot; the old booking stays valid until the course lead approves the new one.
     */
    public function reschedule(Request $request, Registration $registration, CourseInstance $instance): RedirectResponse
    {
        abort_unless($registration->canBeManagedBy($this->contact($request)) && $instance->course_id === $registration->courseInstance->course_id, 404);

        if (! $this->schoolBookings->canBeChangedBySchool($registration)) {
            return redirect()->route('homepage.schools.account')->with('school_error', 'Dieser Termin kann nicht mehr online verschoben werden. Bitte rufen Sie uns an.');
        }

        try {
            $this->schoolBookings->hold($instance, $this->carts->currentOrCreate(), $this->contact($request), replaces: $registration);
        } catch (ReservationException $exception) {
            return redirect()->route('homepage.schools.reschedule', $registration)->with('school_error', $exception->getMessage());
        }

        return redirect()->route('homepage.schools.cart')->with('status', 'Der neue Termin ist für Sie reserviert. Bitte prüfen Sie die Angaben und schicken Sie die Anfrage ab.');
    }

    /**
     * Page behind the link in the teacher's mails. Signed, so it works without logging in.
     */
    public function visit(Registration $registration): View
    {
        abort_unless($registration->isSchoolBooking(), 404);

        $registration->load(['courseInstance.course', 'courseInstance.dates', 'school']);

        return view('homepage.schools.visit', [
            'registration' => $registration,
            'canChange' => $this->schoolBookings->canBeChangedBySchool($registration),
            'confirmUrl' => URL::temporarySignedRoute('homepage.schools.visit.confirm', now()->addHours(2), ['registration' => $registration->id]),
            'cancelUrl' => URL::temporarySignedRoute('homepage.schools.visit.cancel', now()->addHours(2), ['registration' => $registration->id]),
        ]);
    }

    public function confirmVisit(Request $request, Registration $registration): RedirectResponse
    {
        abort_unless($registration->isSchoolBooking() && $registration->status === RegistrationStatus::Confirmed, 404);

        $validated = $request->validate([
            'students' => ['required', 'integer', 'min:1', 'max:200'],
            'remark' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->schoolBookings->confirmVisit($registration, (int) $validated['students'], $validated['remark'] ?? null);

        return redirect()->to($this->schoolBookings->visitUrl($registration))->with('status', 'Vielen Dank! Wir haben Ihre Bestätigung an die Kursleitung weitergegeben.');
    }

    public function cancelVisit(Registration $registration): RedirectResponse
    {
        abort_unless($registration->isSchoolBooking(), 404);

        try {
            $this->schoolBookings->cancelBySchool($registration);
        } catch (ReservationException $exception) {
            return redirect()->to($this->schoolBookings->visitUrl($registration))->with('school_error', $exception->getMessage());
        }

        return redirect()->to($this->schoolBookings->visitUrl($registration))->with('status', 'Der Termin ist storniert. Wir haben die Kursleitung informiert.');
    }

    protected function contact(Request $request): ?Contact
    {
        /** @var Contact|null */
        return $request->user('contact');
    }
}
