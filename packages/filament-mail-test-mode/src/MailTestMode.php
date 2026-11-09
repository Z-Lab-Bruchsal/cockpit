<?php

namespace AlexanderGabriel\FilamentMailTestMode;

use AlexanderGabriel\FilamentMailTestMode\Models\MailTestModeSetting;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;

/**
 * Resolves whether the mail test mode is active and where mails go.
 *
 * The state is read freshly on every call on purpose: long-running queue
 * workers must never keep a stale "disabled" state and send real mails.
 */
class MailTestMode
{
    public function isEnabled(): bool
    {
        return $this->isForcedByEnv() || (bool) $this->storedSetting()?->enabled;
    }

    public function isForcedByEnv(): bool
    {
        return (bool) config('filament-mail-test-mode.enabled');
    }

    public function recipient(): ?string
    {
        $recipient = $this->isForcedByEnv()
            ? config('filament-mail-test-mode.recipient')
            : $this->storedSetting()?->recipient;

        return filled($recipient) ? (string) $recipient : null;
    }

    public function subjectPrefix(): string
    {
        return (string) config('filament-mail-test-mode.subject_prefix', '');
    }

    public function setting(): MailTestModeSetting
    {
        return MailTestModeSetting::current();
    }

    public function enable(string $recipient, ?Authenticatable $user = null): void
    {
        $this->setting()->update([
            'enabled' => true,
            'recipient' => $recipient,
            'activated_by' => $user?->getAuthIdentifier(),
            'activated_at' => now(),
        ]);
    }

    public function disable(): void
    {
        $this->setting()->update([
            'enabled' => false,
            'activated_by' => null,
            'activated_at' => null,
        ]);
    }

    /**
     * Returns null while the package migration has not been run yet.
     */
    protected function storedSetting(): ?MailTestModeSetting
    {
        try {
            return MailTestModeSetting::query()->first();
        } catch (QueryException) {
            return null;
        }
    }
}
