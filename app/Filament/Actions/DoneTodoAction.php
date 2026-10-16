<?php

namespace App\Filament\Actions;

use App\Models\Todo;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class DoneTodoAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->iconButton()
            ->label('erledigt')
            ->tooltip(fn (?Todo $record): string => $record && $this->completesOnlyForCurrentUser($record)
                ? 'Todo für mich als erledigt markieren'
                : 'Todo als erledigt markieren')
            ->icon(Heroicon::Check)
            ->visible(fn (Todo $record): bool => $record->isOwnerOrAssignee(filament()->auth()->user()) && ! $record->isDoneFor(filament()->auth()->user()))
            ->action(fn (Todo $record) => $record->markDoneBy(filament()->auth()->user()));
    }

    protected function completesOnlyForCurrentUser(Todo $todo): bool
    {
        return $todo->individual_completion && $todo->isAssignee(filament()->auth()->user());
    }
}
