<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Services\Registrations\CourseReservationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Decision pages behind the "Freigeben" / "Ablehnen" buttons in approval mails to the course lead.
 * The links are signed; the page only shows a confirmation button so mail scanners cannot decide anything.
 */
class ApprovalController extends Controller
{
    public function __construct(protected CourseReservationService $reservations) {}

    public function show(Request $request, Registration $registration): View
    {
        $registration->load(['courseInstance.course', 'courseInstance.dates', 'enrollments.kid']);
        $decision = $request->query('entscheidung') === 'ablehnen' ? 'ablehnen' : 'freigeben';

        return view('approvals.show', [
            'registration' => $registration,
            'decision' => $decision,
            'isOpen' => $registration->awaitsSiblingApproval() || $registration->awaitsWaitlistDecision(),
            'decideUrl' => URL::temporarySignedRoute('approvals.decide', now()->addHour(), ['registration' => $registration->id, 'entscheidung' => $decision]),
        ]);
    }

    public function decide(Request $request, Registration $registration): RedirectResponse
    {
        $approve = $request->query('entscheidung') !== 'ablehnen';

        match (true) {
            $registration->awaitsSiblingApproval() => $approve
                ? $this->reservations->approveSiblings($registration)
                : $this->reservations->declineSiblings($registration),
            $registration->awaitsWaitlistDecision() => $approve
                ? $this->reservations->approveWaitlistMoveUp($registration)
                : $this->reservations->declineWaitlistMoveUp($registration),
            default => null,
        };

        return redirect()
            ->to(URL::temporarySignedRoute('approvals.show', now()->addDays(30), ['registration' => $registration->id, 'entscheidung' => $request->query('entscheidung')]))
            ->with('decided', $approve ? 'freigegeben' : 'abgelehnt');
    }
}
