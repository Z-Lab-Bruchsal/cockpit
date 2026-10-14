<?php

namespace App\Filament\Resources\Contacts\Actions;

use App\Models\Contact;
use App\Services\Contacts\MagicLinkService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Sends the contact a magic link, e.g. when a parent asks for help logging in.
 */
class SendLoginLinkAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'sendLoginLink';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Anmeldelink senden')
            ->icon(Heroicon::OutlinedEnvelope)
            ->requiresConfirmation()
            ->modalDescription(fn (Contact $record): string => "Es wird ein Anmeldelink an {$record->email} geschickt.")
            ->visible(fn (Contact $record): bool => filled($record->email))
            ->authorize('update')
            ->action(function (Contact $record): void {
                app(MagicLinkService::class)->send($record->email);

                Notification::make()
                    ->success()
                    ->title('Anmeldelink verschickt')
                    ->send();
            });
    }
}
