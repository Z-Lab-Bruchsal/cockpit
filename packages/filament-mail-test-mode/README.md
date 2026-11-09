# Filament Mail Test Mode

Redirects **all** outgoing mails of a Laravel application to a single test recipient. Switch it on in the Filament panel or via environment variables.

## Installation

```bash
composer require alexandergabriel/filament-mail-test-mode
php artisan migrate
```

Register the plugin in your panel:

```php
use AlexanderGabriel\FilamentMailTestMode\FilamentMailTestModePlugin;

$panel->plugins([
    FilamentMailTestModePlugin::make()
        ->navigationGroup('Administration')
        ->authorize(fn (): bool => auth()->user()?->can('View:MailTestModePage') ?? false),
]);
```

## Environment

```dotenv
MAIL_TEST_MODE=true
MAIL_TEST_MODE_RECIPIENT=dev@example.com
```

When set, the environment overrides the panel setting and the test mode cannot be switched off in the panel.

## Behaviour

- `To` is replaced by the test recipient, `Cc` and `Bcc` are removed.
- The original recipients are kept in `X-Original-To`, `X-Original-Cc` and `X-Original-Bcc` headers.
- The subject is prefixed with `[TEST] ` (config `subject_prefix`).
- If the test mode is active without a recipient, mails are discarded and a warning is logged.
- Works for mailables, mail notifications and queued mails (the state is read when the mail is actually sent).
- A red banner is shown on every panel page while the test mode is active (`->banner(false)` to disable).
