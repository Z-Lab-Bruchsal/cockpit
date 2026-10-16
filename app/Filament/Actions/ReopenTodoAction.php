<?php

namespace App\Filament\Actions;

use App\Mail\TodoMail;
use App\Models\Todo;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;

class ReopenTodoAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->iconButton()
            ->label('Wiedereröffnen')
            ->tooltip(fn (?Todo $record): string => $record && $this->reopensOnlyForCurrentUser($record)
                ? 'Todo für mich wieder eröffnen'
                : 'Todo wieder eröffnen')
            ->icon(Heroicon::ArrowUturnDown)
            ->visible(fn (Todo $record): bool => $record->isOwnerOrAssignee(filament()->auth()->user()) && $record->isDoneFor(filament()->auth()->user()))
            ->schema(fn (Todo $record): array => $this->reopensOnlyForCurrentUser($record) ? [] : [
                TextInput::make('notiz')->label('Notiz'),
            ])
            ->action(function (Todo $record, array $data): void {
                if (! $record->reopenFor(filament()->auth()->user())) {
                    return;
                }

                foreach ($record->assignees() as $user) {
                    Mail::to($user)->send(new TodoMail($record, 'Todo wiedereröffnet', 'Eine bereits erledigte Todo wurde wiedereröffnet:', notiz: $data['notiz'] ?? ''));
                }
            });
    }

    protected function reopensOnlyForCurrentUser(Todo $todo): bool
    {
        return $todo->individual_completion && $todo->completedBy()->whereKey(filament()->auth()->id())->exists();
    }
}
