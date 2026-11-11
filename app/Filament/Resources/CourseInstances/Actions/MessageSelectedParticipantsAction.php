<?php

namespace App\Filament\Resources\CourseInstances\Actions;

use App\Filament\Resources\CourseInstances\Actions\Concerns\ComposesParticipantMessage;
use App\Services\Registrations\ParticipantMessageService;
use Filament\Actions\BulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Mails the families of the selected participants.
 */
class MessageSelectedParticipantsAction extends BulkAction
{
    use ComposesParticipantMessage;

    public static function getDefaultName(): ?string
    {
        return 'messageSelectedParticipants';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Nachricht senden')
            ->icon(Heroicon::OutlinedEnvelope)
            ->modalHeading('Nachricht an ausgewählte Teilnehmende')
            ->modalSubmitActionLabel('Senden')
            ->schema(fn (Collection $records, RelationManager $livewire): array => [
                ...static::messageFields(),
                Text::make(static::recipientSummary(
                    app(ParticipantMessageService::class)->recipients(static::courseInstance($livewire), [], $records->modelKeys())->count(),
                ))->columnSpanFull(),
            ])
            ->action(function (Collection $records, array $data, RelationManager $livewire): void {
                $instance = static::courseInstance($livewire);

                static::sendToRecipients($instance, app(ParticipantMessageService::class)->recipients($instance, [], $records->modelKeys()), $data);
            })
            ->deselectRecordsAfterCompletion();
    }
}
