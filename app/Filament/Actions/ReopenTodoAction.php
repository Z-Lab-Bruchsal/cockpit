<?php

namespace App\Filament\Actions;

use App\Mail\TodoMail;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class ReopenTodoAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->iconButton()
            ->label('Wiedereröffnen')
            ->tooltip('Todo wieder eröffnen')
            ->icon(Heroicon::ArrowUturnDown)
            ->visible(function (Model $record) {
                return $record->is_owner_or_assigned() && $record->done_date != null;
            })
            ->schema([
                TextInput::make('notiz')->label('Notiz'),
            ])
            ->action(function (Model $record, array $data) {
                $record->done_date = null;
                $record->save();
                $users = $record->getassignetusers();
                foreach ($users as $user) {
                    Mail::to($user)->send(new TodoMail($record, 'Todo wiedereröffnet', 'Eine bereits erledigte Todo wurde wiedereröffnet:', notiz: $data['notiz']));
                }
            });
    }

}