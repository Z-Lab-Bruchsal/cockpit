<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\Contacts\MagicLinkService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactAuthController extends Controller
{
    public function __construct(protected MagicLinkService $magicLinks) {}

    public function showLoginForm(Request $request): View|RedirectResponse
    {
        if (Auth::guard('contact')->check()) {
            return redirect()->route($this->accountRouteFor(Auth::guard('contact')->user()));
        }

        return view('homepage.auth.login', ['redirectTo' => $request->query('weiter')]);
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'redirect_to' => ['nullable', 'string', 'max:255'],
            // Honeypot: real visitors never fill this hidden field.
            'website' => ['prohibited'],
        ]);

        $this->magicLinks->send($validated['email'], $validated['name'] ?? null, $validated['redirect_to'] ?? null);

        // Same answer for known and unknown addresses, so the form cannot be used to probe for accounts.
        return redirect()->route('homepage.login')->with('magic_link_sent', $validated['email']);
    }

    /**
     * Shows a button instead of logging in directly: mail scanners that open links would otherwise use up the token.
     */
    public function showConfirm(string $token): View
    {
        return view('homepage.auth.confirm', [
            'token' => $token,
            'isValid' => $this->magicLinks->find($token) !== null,
        ]);
    }

    public function login(Request $request, string $token): RedirectResponse
    {
        $contact = $this->magicLinks->consume($token);

        if ($contact === null) {
            return redirect()->route('homepage.login.confirm', $token);
        }

        Auth::guard('contact')->login($contact, remember: true);
        $request->session()->regenerate();

        return redirect($this->magicLinks->redirectFor($token) ?? route($this->accountRouteFor($contact)))
            ->with('status', $contact->canBookForSchool() ? 'Sie sind jetzt angemeldet.' : 'Du bist jetzt angemeldet.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('contact')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('homepage.index');
    }

    public function account(Request $request): View|RedirectResponse
    {
        if ($request->user('contact')?->canBookForSchool()) {
            return redirect()->route('homepage.schools.account');
        }

        return view('homepage.account');
    }

    protected function accountRouteFor(?Contact $contact): string
    {
        return $contact?->canBookForSchool() ? 'homepage.schools.account' : 'homepage.account';
    }
}
