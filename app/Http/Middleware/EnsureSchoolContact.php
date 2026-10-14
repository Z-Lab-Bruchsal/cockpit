<?php

namespace App\Http\Middleware;

use App\Models\Contact;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * School pages are only for school accounts and their teachers. Guests go to the login form,
 * parents to the school landing page.
 */
class EnsureSchoolContact
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Contact|null $contact */
        $contact = $request->user('contact');

        if ($contact === null) {
            return redirect()->route('homepage.login', ['weiter' => $request->getRequestUri()]);
        }

        if (! $contact->canBookForSchool()) {
            return redirect()->route('homepage.schools.index')
                ->with('school_notice', 'Dieser Bereich ist für Schulkonten. Bitte registrieren Sie Ihre Schule oder lassen Sie sich von Ihrer Schule einladen.');
        }

        return $next($request);
    }
}
