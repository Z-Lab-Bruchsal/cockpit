<?php

namespace App\Filament\Resources\CourseInstances\Actions\Concerns;

use App\Models\CourseInstance;
use App\Models\Registration;
use App\Services\Registrations\ParticipantMessageService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Support\Collection;

/**
 * Shared form fields and sending logic of the actions that mail the participants of a course instance.
 */
trait ComposesParticipantMessage
{
    protected static function courseInstance(RelationManager $livewire): CourseInstance
    {
        /** @var CourseInstance */
        return $livewire->getOwnerRecord();
    }

    /**
     * @return array<int, TextInput|Textarea>
     */
    protected static function messageFields(): array
    {
        return [
            TextInput::make('subject')
                ->label('Betreff')
                ->required()
                ->maxLength(255)
                ->default(fn (RelationManager $livewire): string => 'Info zu '.static::courseInstance($livewire)->displayTitle())
                ->columnSpanFull(),
            Textarea::make('body')
                ->label('Nachricht')
                ->helperText('Anrede, Kursname, Termin und Gruß werden automatisch ergänzt. Antworten gehen an die allgemeine Absenderadresse.')
                ->required()
                ->rows(8)
                ->columnSpanFull(),
        ];
    }

    protected static function recipientSummary(int $count): string
    {
        return match ($count) {
            0 => 'Keine Empfänger mit E-Mail-Adresse.',
            1 => '1 Familie bekommt die Nachricht.',
            default => "{$count} Familien bekommen die Nachricht.",
        };
    }

    /**
     * @param  Collection<int, Registration>  $recipients
     * @param  array{subject: string, body: string}  $data
     */
    protected static function sendToRecipients(CourseInstance $instance, Collection $recipients, array $data): void
    {
        if ($recipients->isEmpty()) {
            Notification::make()->warning()->title('Keine Empfänger mit E-Mail-Adresse')->send();

            return;
        }

        app(ParticipantMessageService::class)->send($instance, $data['subject'], $data['body'], $recipients, auth()->user());

        Notification::make()
            ->success()
            ->title($recipients->count() === 1 ? 'An 1 Familie gesendet' : "An {$recipients->count()} Familien gesendet")
            ->send();
    }
}
