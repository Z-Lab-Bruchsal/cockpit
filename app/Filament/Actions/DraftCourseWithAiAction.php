<?php

namespace App\Filament\Actions;

use App\Ai\Agents\CourseDrafter;
use App\Enums\CourseAudience;
use App\Services\Ai\LocalModels;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lets the local LLM draft course texts and settings from a short wish and fills them into the unsaved course form.
 */
class DraftCourseWithAiAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'draftCourseWithAi';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Mit KI entwerfen')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->visible(fn (): bool => CourseDrafter::isAvailable())
            ->modalHeading('Kurs mit KI entwerfen')
            ->modalDescription('Der Entwurf überschreibt Name, Texte, Alter, Dauer, Plätze und Interessen im Formular. Gespeichert wird erst, wenn du speicherst.')
            ->modalSubmitActionLabel('Entwurf erstellen')
            ->schema([
                Textarea::make('wish')
                    ->label('Was für ein Kurs soll es werden?')
                    ->placeholder('z. B. 2h-Kurs Fischertechnik für 10-Jährige, Schwerpunkt Motoren und Zahnräder')
                    ->rows(4)
                    ->required()
                    ->maxLength(2000),
                Select::make('model')
                    ->label('Modell')
                    ->options(fn (): array => LocalModels::options(LocalModels::ROLE_DRAFT))
                    ->default(fn (): string => LocalModels::forRole(LocalModels::ROLE_DRAFT))
                    ->helperText('Ein nicht geladenes Modell wird erst geladen – das dauert etwas länger.')
                    ->selectablePlaceholder(false)
                    ->required(),
            ])
            ->action(function (array $data, CreateRecord|EditRecord $livewire): void {
                try {
                    $draft = CourseDrafter::make()->draft($data['wish'], $data['model']);
                } catch (Throwable $exception) {
                    Log::warning('KI-Kursentwurf fehlgeschlagen.', ['model' => $data['model'], 'error' => $exception->getMessage()]);

                    Notification::make()
                        ->title('Kein Entwurf möglich')
                        ->body("Das Modell {$data['model']} hat nicht geantwortet oder eine ungültige Antwort geliefert. Das Formular ist unverändert.")
                        ->danger()
                        ->send();

                    return;
                }

                $livewire->form->fill([
                    ...$livewire->form->getRawState(),
                    ...array_filter($draft, fn (mixed $value): bool => filled($value)),
                    'audience' => CourseAudience::Kids,
                ]);

                Notification::make()
                    ->title('Entwurf eingefügt')
                    ->body('Bitte alle Tabs prüfen und anpassen, bevor du speicherst.')
                    ->success()
                    ->send();
            });
    }
}
