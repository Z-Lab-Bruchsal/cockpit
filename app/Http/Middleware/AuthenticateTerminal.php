<?php

namespace App\Http\Middleware;

use App\Models\Terminal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a time clock terminal via HTTP Basic Auth against the
 * terminals table and makes it available as the "terminal" request attribute.
 */
class AuthenticateTerminal
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $terminal = Terminal::query()
            ->where('username', (string) $request->getUser())
            ->where('is_active', true)
            ->first();

        if (! $terminal || ! Hash::check((string) $request->getPassword(), $terminal->password)) {
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
