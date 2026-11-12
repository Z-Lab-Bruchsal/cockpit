<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Controllers\Controller;
use App\Mail\TeamContactMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class TeamContactController extends Controller
{
    /**
     * Forwards a message from the business card on the homepage to the team member,
     * without ever revealing their address to the visitor.
     */
    public function send(Request $request, User $member): RedirectResponse
    {
        abort_unless($member->teamProfile?->isContactableViaHomepage(), 404);

        $validated = $request->validateWithBag('teamContact', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'privacy' => ['accepted'],
            // Honeypot: real visitors never fill this hidden field.
            'website' => ['prohibited'],
        ], [
            'privacy.accepted' => 'Bitte stimme der Verarbeitung deiner Angaben zu.',
        ], [
            'name' => 'Name',
            'email' => 'E-Mail-Adresse',
            'message' => 'Nachricht',
        ]);

        Mail::to($member->email, $member->name)->send(new TeamContactMail(
            recipientName: $member->name,
            senderName: $validated['name'],
            senderEmail: $validated['email'],
            body: $validated['message'],
        ));

        return redirect()->to(url()->previous().'#team-'.$member->id)->with('team_contact_sent', $member->id);
    }
}
