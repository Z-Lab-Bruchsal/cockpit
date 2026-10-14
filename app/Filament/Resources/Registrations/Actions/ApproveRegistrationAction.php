<?php

namespace App\Filament\Resources\Registrations\Actions;

use App\Models\Enrollment;
use App\Models\Registration;
use App\Services\Registrations\CourseReservationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Approves siblings beyond the free seats, or lets a too-big waitlist family move up.
 */
class ApproveRegistrationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'approveRegistration';
    }

    /**
     * Works on registrations and, in participant lists, on enrollments of the registration.
     */
    protected static function registration(Model $record): Registration
    {
        return $record instanceof Enrollment ? $record->registration : $record;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Freigeben')
            ->icon(Heroicon::OutlinedHandThumbUp)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(fn (Model $record): string => static::registration($record)->awaitsSiblingApproval()
                ? 'Alle Geschwister bekommen einen Platz, der Kurs wird dadurch überbucht. Die Familie wird per E-Mail informiert.'
                : 'Die Familie bekommt alle Plätze angeboten, der Kurs wird dadurch überbucht.')
            ->visible(fn (Model $record): bool => static::registration($record)->awaitsSiblingApproval() || static::registration($record)->awaitsWaitlistDecision())
            ->authorize(fn (Model $record): bool => auth()->user()?->can('update', static::registration($record)) ?? false)
            ->action(function (Model $record): void {
                $reservations = app(CourseReservationService::class);

                static::registration($record)->awaitsSiblingApproval()
                    ? $reservations->approveSiblings(static::registration($record))
                    : $reservations->approveWaitlistMoveUp(static::registration($record));

                Notification::make()->success()->title('Freigegeben')->send();
            });
    }
}
