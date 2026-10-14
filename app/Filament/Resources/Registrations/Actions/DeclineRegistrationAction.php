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
 * Declines siblings beyond the free seats (they go on the waitlist), or skips a too-big waitlist family
 * for the current free seats (it keeps its position).
 */
class DeclineRegistrationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'declineRegistration';
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

        $this->label('Ablehnen')
            ->icon(Heroicon::OutlinedHandThumbDown)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn (Model $record): string => match (true) {
                static::registration($record)->awaitsSiblingApproval() && static::registration($record)->courseInstance->waitlist_enabled => 'Die Familie kommt gemeinsam auf die Warteliste und wird per E-Mail informiert.',
                static::registration($record)->awaitsSiblingApproval() => 'Die Anmeldung wird abgelehnt und die Familie per E-Mail informiert.',
                default => 'Die freien Plätze gehen an die nächsten Familien auf der Warteliste. Diese Familie bleibt vorne auf der Warteliste.',
            })
            ->visible(fn (Model $record): bool => static::registration($record)->awaitsSiblingApproval() || static::registration($record)->awaitsWaitlistDecision())
            ->authorize(fn (Model $record): bool => auth()->user()?->can('update', static::registration($record)) ?? false)
            ->action(function (Model $record): void {
                $reservations = app(CourseReservationService::class);

                static::registration($record)->awaitsSiblingApproval()
                    ? $reservations->declineSiblings(static::registration($record))
                    : $reservations->declineWaitlistMoveUp(static::registration($record));

                Notification::make()->success()->title('Abgelehnt')->send();
            });
    }
}
