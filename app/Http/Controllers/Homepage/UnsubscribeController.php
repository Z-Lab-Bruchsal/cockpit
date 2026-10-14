<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Unsubscribing from course mails. GET shows a button (mail scanners must not unsubscribe anybody),
 * POST unsubscribes – also used by mail clients for one-click unsubscribe (RFC 8058), hence without CSRF token.
 */
class UnsubscribeController extends Controller
{
    public function show(string $token): View
    {
        return view('homepage.unsubscribe', [
            'contact' => $this->contact($token),
            'token' => $token,
        ]);
    }

    public function unsubscribe(string $token): RedirectResponse|Response
    {
        $contact = $this->contact($token);
        $contact->update(['interest_mails_consent_at' => null]);

        if (request()->input('List-Unsubscribe') === 'One-Click') {
            return response()->noContent();
        }

        return redirect()->route('homepage.unsubscribe', $token)->with('unsubscribed', true);
    }

    protected function contact(string $token): Contact
    {
        return Contact::query()->where('unsubscribe_token', $token)->firstOrFail();
    }
}
