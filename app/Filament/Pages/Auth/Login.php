<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cockpit login with brute-force protection on failed attempts, per account and per IP,
 * on top of Filament's own short per-IP throttle.
 */
class Login extends BaseLogin
{
    public const int MAX_FAILURES_PER_EMAIL = 5;

    public const int EMAIL_LOCK_SECONDS = 15 * 60;

    public const int MAX_FAILURES_PER_IP = 20;

    public const int IP_LOCK_SECONDS = 60 * 60;

    public function authenticate(): ?LoginResponse
    {
        foreach ([$this->emailFailuresKey() => self::MAX_FAILURES_PER_EMAIL, $this->ipFailuresKey() => self::MAX_FAILURES_PER_IP] as $key => $maxFailures) {
            if (RateLimiter::tooManyAttempts($key, $maxFailures)) {
                Log::warning('Cockpit-Login gesperrt', $this->logContext());

                throw ValidationException::withMessages([
                    'data.email' => 'Zu viele fehlgeschlagene Anmeldeversuche. Bitte in '.max(1, (int) ceil(RateLimiter::availableIn($key) / 60)).' Minuten erneut versuchen.',
                ]);
            }
        }

        $response = parent::authenticate();

        if ($response !== null) {
            RateLimiter::clear($this->emailFailuresKey());
        }

        return $response;
    }

    protected function throwFailureValidationException(): never
    {
        RateLimiter::hit($this->emailFailuresKey(), self::EMAIL_LOCK_SECONDS);
        RateLimiter::hit($this->ipFailuresKey(), self::IP_LOCK_SECONDS);

        Log::warning('Cockpit-Login fehlgeschlagen', $this->logContext());

        parent::throwFailureValidationException();
    }

    protected function attemptedEmail(): string
    {
        return Str::lower(trim((string) ($this->data['email'] ?? '')));
    }

    protected function emailFailuresKey(): string
    {
        return 'cockpit-login-failed:email:'.sha1($this->attemptedEmail());
    }

    protected function ipFailuresKey(): string
    {
        return 'cockpit-login-failed:ip:'.request()->ip();
    }

    /**
     * @return array{email: string, ip: ?string}
     */
    protected function logContext(): array
    {
        return ['email' => $this->attemptedEmail(), 'ip' => request()->ip()];
    }
}
