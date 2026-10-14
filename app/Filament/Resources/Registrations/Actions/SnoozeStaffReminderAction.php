<?php

namespace App\Filament\Resources\Registrations\Actions;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Models\User;
use App\Services\Schools\SchoolBookingService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * Moves the course lead's reminder for a school visit, optionally with a note on the course instance.
 */
class SnoozeStaffReminderAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'snoozeStaffReminder';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Erinnerung verschieben')
            ->icon(Heroicon::OutlinedBellSnooze)
            ->color('gray')
            ->fillForm(fn (Registration $record): array => [
                'remind_at' => ($record->staff_remind_at?->isFuture() ? $record->staff_remind_at : now()->addDays(3)->setTime(8, 0)),
            ])
            ->schema([
                DateTimePicker::make('remind_at')
                    ->label('Erneut erinnern am')
                    ->seconds(false)
                    ->required()
                    ->after('now'),
                Textarea::make('note')
                    ->label('Notiz')
                    ->helperText('Wird als Notiz an der Durchführung gespeichert, z. B. aus einem Telefonat mit der Schule.')
                    ->rows(3),
            ])
            ->visible(fn (Registration $record): bool => $record->isSchoolBooking() && $record->status === RegistrationStatus::Confirmed)
            ->authorize('update')
            ->action(function (Registration $record, array $data): void {
                /** @var User|null $user */
                $user = auth()->user();

                app(SchoolBookingService::class)->snoozeStaffReminder($record, Carbon::parse($data['remind_at']), $data['note'] ?? null, $user);

                Notification::make()->success()->title('Erinnerung verschoben')->send();
            });
    }
}
