<?php

namespace App\Filament\Resources\Registrations\Actions;

use App\Models\Enrollment;
use App\Models\Registration;
use App\Services\Schools\SchoolBookingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Confirms a school class request. A moved booking replaces the old date only now.
 */
class ApproveSchoolRequestAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'approveSchoolRequest';
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

        $this->label('Bestätigen')
            ->icon(Heroicon::OutlinedHandThumbUp)
            ->color('success')
            ->modalHeading('Schulanfrage bestätigen')
            ->modalDescription(fn (Model $record): string => static::registration($record)->replaces_registration_id
                ? 'Die Schule wird per E-Mail informiert. Ihr bisheriger Termin wird storniert und ist danach wieder frei.'
                : 'Die Schule wird per E-Mail informiert.')
            ->schema([
                Textarea::make('note')
                    ->label('Nachricht an die Schule')
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->visible(fn (Model $record): bool => static::registration($record)->awaitsSchoolDecision())
            ->authorize(fn (Model $record): bool => auth()->user()?->can('update', static::registration($record)) ?? false)
            ->action(function (Model $record, array $data): void {
                app(SchoolBookingService::class)->approve(static::registration($record), $data['note'] ?? null);

                Notification::make()->success()->title('Bestätigt')->send();
            });
    }
}
