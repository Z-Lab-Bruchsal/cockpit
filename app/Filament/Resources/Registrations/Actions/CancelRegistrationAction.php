<?php

namespace App\Filament\Resources\Registrations\Actions;

use App\Models\Registration;
use App\Services\Registrations\CourseReservationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Cancels a whole registration; freed seats are offered to the waitlist.
 */
class CancelRegistrationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'cancelRegistration';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Stornieren')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Alle Plätze dieser Anmeldung werden freigegeben und der Warteliste angeboten.')
            ->schema([
                Toggle::make('notify')
                    ->label('Eltern per E-Mail informieren')
                    ->default(true),
            ])
            ->visible(fn (Registration $record): bool => $record->status->isActive())
            ->authorize('update')
            ->action(function (Registration $record, array $data): void {
                app(CourseReservationService::class)->cancel($record, notify: (bool) $data['notify']);

                Notification::make()->success()->title('Anmeldung storniert')->send();
            });
    }
}
