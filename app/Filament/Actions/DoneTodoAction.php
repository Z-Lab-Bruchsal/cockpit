<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class DoneTodoAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->iconButton()
            ->label('erledigt')
            ->tooltip('Todo als erledigt markieren')
            ->icon(Heroicon::Check)
            ->visible(function (Model $record) {
                return $record->is_owner_or_assigned() && $record->done_date == null;
            })
            ->action(function (Model $record) {
                $record->done_date = now()->today();
                $record->save();
            });
    }

}