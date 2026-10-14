<?php

namespace App\Filament\Resources\CourseInstances\Actions;

use App\Enums\CourseInstanceStatus;
use App\Models\CourseInstance;
use App\Models\Interest;
use App\Services\Notifications\InterestNotificationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * Mails parents whose children match the course's interests and age; shows the number of recipients before sending.
 */
class NotifyInterestedAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'notifyInterested';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Interessierte benachrichtigen')
            ->icon(Heroicon::OutlinedMegaphone)
            ->modalHeading('Interessierte benachrichtigen')
            ->modalDescription('Der Kurs wird für alle Eltern mit Einwilligung vorgemerkt, bei denen mindestens ein Kind passt. Kinder ohne Geburtsdatum passen unabhängig vom Alter. Die Eltern bekommen eine Sammelmail, sobald eine Weile kein weiterer Kurs dazugekommen ist.')
            ->modalSubmitActionLabel('Vormerken')
            ->fillForm(fn (CourseInstance $record): array => app(InterestNotificationService::class)->defaultFilters($record))
            ->schema([
                Grid::make(3)->schema([
                    Select::make('interest_ids')
                        ->label('Kinder mit diesen Interessen')
                        ->placeholder('Alle Kinder')
                        ->options(fn (): array => Interest::query()->active()->pluck('name', 'id')->all())
                        ->multiple()
                        ->live(),
                    TextInput::make('min_age')
                        ->label('Alter von')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->live(onBlur: true),
                    TextInput::make('max_age')
                        ->label('Alter bis')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->gte('min_age')
                        ->live(onBlur: true),
                ])->columnSpanFull(),
                Text::make(fn (Get $get, CourseInstance $record): string => static::recipientSummary($record, $get))
                    ->columnSpanFull(),
            ])
            ->visible(fn (CourseInstance $record): bool => $record->status === CourseInstanceStatus::Published && $record->course->is_published)
            ->authorize('update')
            ->action(function (CourseInstance $record, array $data): void {
                $count = app(InterestNotificationService::class)->notify(
                    $record,
                    $data['interest_ids'] ?? [],
                    filled($data['min_age'] ?? null) ? (int) $data['min_age'] : null,
                    filled($data['max_age'] ?? null) ? (int) $data['max_age'] : null,
                    auth()->user(),
                );

                Notification::make()
                    ->success()
                    ->title($count === 0 ? 'Niemand zu benachrichtigen' : ($count === 1 ? 'Für 1 Familie vorgemerkt' : "Für {$count} Familien vorgemerkt"))
                    ->body($count > 0 ? 'Die Sammelmails gehen automatisch raus.' : null)
                    ->send();
            });
    }

    protected static function recipientSummary(CourseInstance $instance, Get $get): string
    {
        $service = app(InterestNotificationService::class);
        $minAge = filled($get('min_age')) ? (int) $get('min_age') : null;
        $maxAge = filled($get('max_age')) ? (int) $get('max_age') : null;

        $new = $service->recipients($instance, $get('interest_ids') ?? [], $minAge, $maxAge)->count();
        $already = $service->recipients($instance, $get('interest_ids') ?? [], $minAge, $maxAge, includeAlreadyNotified: true)->count() - $new;

        return match (true) {
            $new === 0 && $already === 0 => 'Keine passenden Familien mit Einwilligung gefunden.',
            $new === 0 => "Alle {$already} passenden Familien sind schon vorgemerkt oder benachrichtigt.",
            default => ($new === 1 ? '1 Familie wird' : "{$new} Familien werden").' benachrichtigt.'.($already > 0 ? " {$already} sind schon vorgemerkt oder benachrichtigt." : ''),
        };
    }
}
