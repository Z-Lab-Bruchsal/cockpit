<?php

namespace App\Filament\Resources\Registrations\Actions;

use App\Models\Registration;
use App\Models\User;
use App\Services\Schools\SchoolBookingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Adds a note to the course instance of a school booking, e.g. about directions or special needs.
 */
class AddInstanceNoteAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'addInstanceNote';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Notiz hinzufügen')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->schema([
                Textarea::make('body')
                    ->label('Notiz zur Durchführung')
                    ->rows(4)
                    ->required(),
            ])
            ->visible(fn (Registration $record): bool => $record->isSchoolBooking())
            ->authorize('update')
            ->action(function (Registration $record, array $data): void {
                /** @var User|null $user */
                $user = auth()->user();

                app(SchoolBookingService::class)->addNote($record->courseInstance, $data['body'], $user, $record);

                Notification::make()->success()->title('Notiz gespeichert')->send();
            });
    }
}
