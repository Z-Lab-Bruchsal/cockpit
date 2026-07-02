<?php

namespace App\Filament\Resources\Todos\Pages;

use App\Filament\Actions\DoneTodoAction;
use App\Filament\Actions\ReopenTodoAction;
use App\Filament\Actions\SetReminderAction;
use App\Filament\Resources\Todos\TodoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditTodo extends EditRecord
{
    protected static string $resource = TodoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DoneTodoAction::make('done'),
            ReopenTodoAction::make('reopen'),
            SetReminderAction::make([]),
            DeleteAction::make()->iconButton()->icon(Heroicon::Trash),
        ];
    }
}
