<?php

namespace App\Filament\Actions;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Password;

/**
 * Sends a user the same password reset mail the login page's "forgot password" flow sends.
 */
class SendPasswordResetLinkAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'sendPasswordResetLink';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Passwort-Reset-Link senden')
            ->icon(Heroicon::Key)
            ->requiresConfirmation()
            ->modalHeading('Passwort-Reset-Link senden')
            ->modalDescription(fn (User $record): string => "{$record->email} bekommt eine E-Mail mit einem Link, über den ein neues Passwort gesetzt werden kann.")
            ->modalSubmitActionLabel('Senden')
            ->authorize(fn (User $record): bool => (bool) filament()->auth()->user()?->can('update', $record))
            // The reset page refuses users without cockpit access, so a link would be useless for them.
            ->visible(fn (User $record): bool => $record->canAccessPanel(Filament::getPanel('admin')))
            ->action(function (User $record): void {
                self::sendLink($record);

                Notification::make()
                    ->title("Reset-Link an {$record->email} gesendet")
                    ->success()
                    ->send();
            });
    }

    public static function sendLink(User $user): void
    {
        $token = Password::broker(Filament::getAuthPasswordBroker())->createToken($user);

        $notification = app(ResetPassword::class, ['token' => $token]);
        $notification->url = Filament::getResetPasswordUrl($token, $user);

        $user->notify($notification);
    }
}
