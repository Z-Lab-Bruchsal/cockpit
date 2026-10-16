<?php

namespace App\Http\Controllers\Homepage;

use App\Enums\EnrollmentStatus;
use App\Enums\RegistrationStatus;
use App\Exceptions\ReservationException;
use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\CourseInstance;
use App\Models\Registration;
use App\Services\Registrations\CourseReservationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    /**
     * Session key holding the token of the running booking per course instance.
     */
    public const SESSION_KEY = 'booking_holds';

    public function __construct(protected CourseReservationService $reservations) {}

    /**
     * "Buchen" on a course page: holds the seats and opens the booking page with the countdown.
     * A second click on the same course continues the running booking instead of holding more seats.
     */
    public function start(Request $request, CourseInstance $instance): RedirectResponse
    {
        abort_unless($instance->course?->is_published && ! $instance->course->isForSchoolClasses(), 404);

        $backToCourse = redirect()->to($instance->course->url().'#durchfuehrung-'.$instance->id);
        $contact = $this->contact($request);

        if ($contact?->canBookForSchool()) {
            return $backToCourse->withErrors(['seats' => 'Mit einem Schulkonto können keine Kurse für einzelne Kinder gebucht werden. Bitte buchen Sie über den Bereich für Schulen.'], 'instance'.$instance->id);
        }

        $validated = $request->validate([
            'seats' => ['required', 'integer', 'min:1', 'max:'.CompanySetting::current()->max_seats_per_registration],
        ]);

        $runningToken = $request->session()->get(self::SESSION_KEY.'.'.$instance->id);

        if (is_string($runningToken) && $this->reservations->findHeld($runningToken) !== null) {
            return redirect()->route('homepage.booking.show', $runningToken);
        }

        try {
            [, $plainToken] = $this->reservations->hold($instance, (int) $validated['seats'], $contact);
        } catch (ReservationException $exception) {
            return $backToCourse->withErrors(['seats' => $exception->getMessage()], 'instance'.$instance->id);
        }

        $request->session()->put(self::SESSION_KEY.'.'.$instance->id, $plainToken);

        return redirect()->route('homepage.booking.show', $plainToken);
    }

    /**
     * Booking page with countdown. Once the time has run out, it explains that the seats were released.
     */
    public function showBooking(string $token): View
    {
        if (($registration = $this->reservations->findHeld($token)) !== null) {
            abort_if($registration->courseInstance->course->isForSchoolClasses(), 404);

            return view('homepage.booking.show', ['registration' => $registration, 'token' => $token]);
        }

        return $this->expired($token);
    }

    public function cancelHold(Request $request, string $token): RedirectResponse
    {
        $registration = $this->reservations->findHeld($token);

        abort_if($registration === null || $registration->courseInstance->course->isForSchoolClasses(), 404);

        $this->reservations->releaseHold($registration);
        $request->session()->forget(self::SESSION_KEY.'.'.$registration->course_instance_id);

        return redirect()->to($registration->courseInstance->course->url().'#durchfuehrung-'.$registration->course_instance_id)
            ->with('status', 'Die Buchung wurde abgebrochen, die Plätze sind wieder frei.');
    }

    public function done(): View
    {
        return view('homepage.booking.done', [
            'confirmedDirectly' => (bool) session('registration_confirmed_directly'),
            'email' => session('registration_email'),
        ]);
    }

    /**
     * Shows a button instead of confirming directly, so mail scanners opening the link do not confirm anything.
     */
    public function show(string $token): View
    {
        return view('homepage.registrations.confirm', [
            'token' => $token,
            'registrations' => $this->reservations->findByToken($token),
            'confirmed' => session('registrations_confirmed', false),
        ]);
    }

    public function confirm(string $token): RedirectResponse
    {
        $confirmed = $this->reservations->confirm($token);

        return redirect()
            ->route('homepage.registration.show', $token)
            ->with('registrations_confirmed', $confirmed->isNotEmpty());
    }

    public function manage(string $token): View
    {
        $registration = $this->reservations->findByManageToken($token);

        abort_if($registration === null, 404);

        return view('homepage.registrations.manage', [
            'token' => $token,
            'registration' => $registration,
        ]);
    }

    public function cancel(Request $request, string $token): RedirectResponse
    {
        $registration = $this->reservations->findByManageToken($token);

        abort_if($registration === null || ! $registration->status->isActive(), 404);

        $enrollment = $request->filled('enrollment')
            ? $registration->enrollments()->whereKey($request->integer('enrollment'))->whereNotIn('status', [EnrollmentStatus::Cancelled, EnrollmentStatus::Expired])->firstOrFail()
            : null;

        $this->reservations->cancel($registration, $enrollment);

        return redirect()->route('homepage.registration.manage', $token)->with('status', 'Die Abmeldung ist erledigt.');
    }

    /**
     * Logged-in parents can cancel their registrations from their account.
     */
    public function cancelFromAccount(Request $request, Registration $registration): RedirectResponse
    {
        abort_unless($registration->contact_id === $request->user('contact')?->id && in_array($registration->status, [RegistrationStatus::Confirmed, RegistrationStatus::AwaitingApproval], true), 404);

        $enrollment = $request->filled('enrollment')
            ? $registration->enrollments()->whereKey($request->integer('enrollment'))->firstOrFail()
            : null;

        $this->reservations->cancel($registration, $enrollment);

        return redirect()->route('homepage.account')->with('status', 'Die Abmeldung ist erledigt.');
    }

    /**
     * Shown for a booking page whose time has run out (or that was cancelled) – with the way back to the course.
     */
    protected function expired(string $token): View
    {
        $registration = Registration::query()
            ->where('hold_token_hash', Registration::hashToken($token))
            ->with('courseInstance.course')
            ->first();

        abort_if($registration === null, 404);

        $course = $registration->courseInstance->course;

        return view('homepage.booking.expired', [
            'isSchool' => $course->isForSchoolClasses(),
            'backUrl' => $course->isForSchoolClasses()
                ? route('homepage.schools.slots').'#termin-'.$registration->course_instance_id
                : $course->url().'#durchfuehrung-'.$registration->course_instance_id,
        ]);
    }

    protected function contact(Request $request): ?Contact
    {
        /** @var Contact|null */
        return $request->user('contact');
    }
}
