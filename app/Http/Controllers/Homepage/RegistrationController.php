<?php

namespace App\Http\Controllers\Homepage;

use App\Enums\CourseAudience;
use App\Enums\EnrollmentStatus;
use App\Enums\RegistrationStatus;
use App\Exceptions\ReservationException;
use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\CourseInstance;
use App\Models\Registration;
use App\Services\Registrations\CartManager;
use App\Services\Registrations\CourseReservationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function __construct(
        protected CourseReservationService $reservations,
        protected CartManager $carts,
    ) {}

    public function addToCart(Request $request, CourseInstance $instance): RedirectResponse
    {
        $validated = $request->validate([
            'seats' => ['required', 'integer', 'min:1', 'max:'.CompanySetting::current()->max_seats_per_registration],
        ]);

        abort_unless($instance->course?->is_published && ! $instance->course->isForSchoolClasses(), 404);

        try {
            $registration = $this->reservations->hold($instance, (int) $validated['seats'], $this->carts->currentOrCreate());
        } catch (ReservationException $exception) {
            return redirect()
                ->to($instance->course->url().'#durchfuehrung-'.$instance->id)
                ->withErrors(['seats' => $exception->getMessage()], 'instance'.$instance->id);
        }

        $settings = CompanySetting::current();
        $coursesInCart = $registration->cart->heldRegistrations()->count();

        $status = match (true) {
            $registration->isOnWaitlist() => 'Der Kurs ist ausgebucht – du kommst auf die Warteliste.',
            $registration->needs_approval => 'Die Plätze sind für dich reserviert. Weil nicht mehr genug Plätze für alle Geschwister frei sind, prüfen wir die Anmeldung nach dem Absenden.',
            default => 'Die Plätze sind für dich reserviert.',
        };

        $redirect = redirect()->route('homepage.cart')->with('status', $status);

        if ($settings->strictestBookingLimit() !== null && $coursesInCart > $settings->strictestBookingLimit()) {
            $redirect->with('booking_limit_warning', "Du hast jetzt {$coursesInCart} Kurse im Warenkorb. Pro Kind sind aber {$settings->bookingLimitText()} möglich – bei Geschwistern ist das kein Problem, sonst entferne bitte einen Kurs.");
        }

        return $redirect;
    }

    public function removeFromCart(Registration $registration): RedirectResponse
    {
        $cart = $this->carts->current();

        abort_unless($cart !== null && $registration->cart_id === $cart->id, 404);

        $this->reservations->removeFromCart($registration);

        return redirect()->route('homepage.cart');
    }

    public function cart(): View|RedirectResponse
    {
        // School slots are checked out with the school form.
        if ($this->carts->current()?->heldRegistrations()->whereHas('courseInstance.course', fn ($courses) => $courses->where('audience', CourseAudience::SchoolClass))->exists()) {
            return redirect()->route('homepage.schools.cart');
        }

        return view('homepage.registrations.cart');
    }

    public function done(): View
    {
        return view('homepage.registrations.done', [
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
}
