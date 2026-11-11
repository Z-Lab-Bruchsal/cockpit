<?php

namespace App\Filament\Resources\CourseInstances\Actions;

use App\Enums\EnrollmentStatus;
use App\Filament\Resources\CourseInstances\Actions\Concerns\ComposesParticipantMessage;
use App\Services\Registrations\ParticipantMessageService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * Mails all participants of the course instance with the chosen enrollment statuses.
 */
class MessageParticipantsAction extends Action
{
    use ComposesParticipantMessage;

    public static function getDefaultName(): ?string
    {
        return 'messageParticipants';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Nachricht an Teilnehmende')
            ->icon(Heroicon::OutlinedEnvelope)
            ->modalHeading('Nachricht an Teilnehmende')
            ->modalSubmitActionLabel('Senden')
            ->schema([
                CheckboxList::make('statuses')
                    ->label('An')
                    ->options(collect(ParticipantMessageService::selectableStatuses())
                        ->mapWithKeys(fn (EnrollmentStatus $status): array => [$status->value => $status->getLabel()])
                        ->all())
                    ->default([EnrollmentStatus::Confirmed->value])
                    ->required()
                    ->columns(3)
                    ->live()
                    ->columnSpanFull(),
                ...static::messageFields(),
                Text::make(fn (Get $get, RelationManager $livewire): string => static::recipientSummary(
                    app(ParticipantMessageService::class)->recipients(static::courseInstance($livewire), $get('statuses') ?? [])->count(),
                ))->columnSpanFull(),
            ])
            ->action(function (array $data, RelationManager $livewire): void {
                $instance = static::courseInstance($livewire);

                static::sendToRecipients($instance, app(ParticipantMessageService::class)->recipients($instance, $data['statuses'] ?? []), $data);
            });
    }
}
