<?php

namespace App\Filament\Resources\CourseInstances\RelationManagers;

use App\Enums\EnrollmentStatus;
use App\Exceptions\ReservationException;
use App\Filament\Resources\Registrations\Actions\ApproveRegistrationAction;
use App\Filament\Resources\Registrations\Actions\DeclineRegistrationAction;
use App\Filament\Resources\Registrations\RegistrationResource;
use App\Models\CourseInstance;
use App\Models\Enrollment;
use App\Models\Kid;
use App\Services\Registrations\CourseReservationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'Teilnehmende';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var CourseInstance $ownerRecord */
        return $ownerRecord->takenSeats().' / '.$ownerRecord->capacity;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['kid', 'registration.contact']))
            ->defaultSort(fn (Builder $query): Builder => $query->orderByRaw('waitlist_position is not null')->orderBy('waitlist_position')->orderBy('id'))
            ->columns([
                TextColumn::make('kid_name')
                    ->label('Kind')
                    ->state(fn (Enrollment $record): string => $record->displayKidName())
                    ->searchable(),
                TextColumn::make('age')
                    ->label('Alter')
                    ->state(fn (Enrollment $record): ?int => $record->kidAge($this->getOwnerRecord()->firstDate?->starts_at))
                    ->suffix(' Jahre')
                    ->placeholder('—'),
                TextColumn::make('registration.contact_name')
                    ->label('Kontakt')
                    ->state(fn (Enrollment $record): string => $record->registration->displayContactName())
                    ->description(fn (Enrollment $record): ?string => $record->registration->phone),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('waitlist_position')
                    ->label('Warteliste')
                    ->placeholder('—'),
                TextColumn::make('registration.message')
                    ->label('Nachricht')
                    ->limit(40)
                    ->tooltip(fn (Enrollment $record): ?string => $record->registration->message)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(EnrollmentStatus::class)
                    ->multiple()
                    ->default([
                        EnrollmentStatus::Confirmed->value,
                        EnrollmentStatus::Pending->value,
                        EnrollmentStatus::Waitlisted->value,
                        EnrollmentStatus::Offered->value,
                        EnrollmentStatus::AwaitingApproval->value,
                    ]),
            ])
            ->headerActions([
                Action::make('registerKid')
                    ->label('Kind anmelden')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->schema([
                        Select::make('kid_id')
                            ->label('Kind')
                            ->options(fn (): array => Kid::query()->with('contacts')->orderBy('name')->get()
                                ->mapWithKeys(fn (Kid $kid): array => [$kid->id => $kid->name.($kid->age() !== null ? " ({$kid->age()} J.)" : '').($kid->contacts->isNotEmpty() ? ' – '.$kid->contacts->pluck('name')->implode(', ') : '')])
                                ->all())
                            ->searchable()
                            ->required(),
                        Toggle::make('ignore_capacity')
                            ->label('Auch wenn ausgebucht (überbuchen)')
                            ->helperText('Sonst kommt das Kind bei vollem Kurs auf die Warteliste.'),
                    ])
                    ->action(function (array $data): void {
                        try {
                            $registration = app(CourseReservationService::class)->registerManually(
                                $this->getOwnerRecord(),
                                Kid::query()->findOrFail($data['kid_id']),
                                ignoreCapacity: (bool) $data['ignore_capacity'],
                            );
                        } catch (ReservationException $exception) {
                            Notification::make()->danger()->title($exception->getMessage())->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title($registration->isOnWaitlist() ? 'Auf die Warteliste gesetzt' : 'Angemeldet')
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('openRegistration')
                    ->label('Anmeldung öffnen')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->url(fn (Enrollment $record): string => RegistrationResource::getUrl('view', ['record' => $record->registration_id])),
                ApproveRegistrationAction::make(),
                DeclineRegistrationAction::make(),
                Action::make('confirmFromWaitlist')
                    ->label('Von der Warteliste aufnehmen')
                    ->icon(Heroicon::OutlinedArrowUpCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Das Kind bekommt sofort einen festen Platz – auch wenn der Kurs dadurch überbucht ist.')
                    ->visible(fn (Enrollment $record): bool => in_array($record->status, [EnrollmentStatus::Waitlisted, EnrollmentStatus::Offered], true))
                    ->action(fn (Enrollment $record) => $record->update(['status' => EnrollmentStatus::Confirmed, 'expires_at' => null])),
                Action::make('cancelEnrollment')
                    ->label('Abmelden')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        Toggle::make('notify')
                            ->label('Eltern per E-Mail informieren')
                            ->default(true),
                    ])
                    ->visible(fn (Enrollment $record): bool => ! in_array($record->status, [EnrollmentStatus::Cancelled, EnrollmentStatus::Expired], true))
                    ->action(function (Enrollment $record, array $data): void {
                        app(CourseReservationService::class)->cancel($record->registration, $record, notify: (bool) $data['notify']);

                        Notification::make()->success()->title('Abgemeldet')->send();
                    }),
            ]);
    }
}
