<?php

namespace App\Http\Middleware;

use App\Models\Terminal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a time clock terminal via HTTP Basic Auth against the
 * terminals table and makes it available as the "terminal" request attribute.
 * Failed attempts are counted per IP, so passwords cannot be brute forced.
 */
class AuthenticateTerminal
{
    public const MAX_FAILED_ATTEMPTS_PER_MINUTE = 10;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rateLimiterKey = 'terminal-auth-failed:'.$request->ip();

        if (RateLimiter::tooManyAttempts($rateLimiterKey, self::MAX_FAILED_ATTEMPTS_PER_MINUTE)) {
            return response()->json(
                ['status' => 'error', 'subject' => 'Zu viele Versuche', 'text' => 'Bitte später erneut versuchen'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => RateLimiter::availableIn($rateLimiterKey)],
            );
        }

        $terminal = Terminal::query()
            ->where('username', (string) $request->getUser())
            ->where('is_active', true)
            ->first();

        if (! $terminal || ! Hash::check((string) $request->getPassword(), $terminal->password)) {
            RateLimiter::hit($rateLimiterKey, 60);

            return response()->json(
                ['status' => 'error', 'subject' => 'Nicht angemeldet', 'text' => 'Terminal-Zugangsdaten prüfen'],
                Response::HTTP_UNAUTHORIZED,
                ['WWW-Authenticate' => 'Basic realm="terminal"'],
            );
        }

        $terminal->forceFill([
            'last_seen_at' => now(),
            'ip_address' => $request->ip(),
        ])->saveQuietly();

        $request->attributes->set('terminal', $terminal);

        return $next($request);
    }
}
