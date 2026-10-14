<?php

namespace App\Filament\Resources\Registrations\Actions;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Services\Registrations\CourseReservationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Confirms a registration without the parents' email link, e.g. after they called.
 */
class ConfirmRegistrationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'confirmRegistration';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Bestätigen')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Die Anmeldung wird ohne den Bestätigungslink aus der E-Mail verbindlich. Die Eltern erhalten eine Bestätigung.')
            ->visible(fn (Registration $record): bool => $record->status === RegistrationStatus::PendingConfirmation)
            ->authorize('update')
            ->action(function (Registration $record): void {
                app(CourseReservationService::class)->confirmManually($record);

                Notification::make()->success()->title('Anmeldung bestätigt')->send();
            });
    }
}
