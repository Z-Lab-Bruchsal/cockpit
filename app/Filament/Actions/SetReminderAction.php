<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class SetReminderAction extends ActionGroup
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Erinnerung')
            ->tooltip('Wiedervorlage setzen')
            ->icon(Heroicon::BellSnooze)
            ->iconButton()
            ->visible(function (Model $record) {
                return $record->is_owner_or_assigned();
            })
            ->actions([
                Action::make('reminderIn1Day')
                    ->label('in 1 Tag')
                    ->action(fn (Model $record) => $record->update(['follow_up' => now()->addDay()])),
                Action::make('reminderIn3Days')
                    ->label('in 3 Tagen')
                    ->action(fn (Model $record) => $record->update(['follow_up' => now()->addDays(3)])),
                Action::make('reminderIn1Week')
                    ->label('in 1 Woche')
                    ->action(fn (Model $record) => $record->update(['follow_up' => now()->addWeek()])),
                Action::make('reminderIn1Month')
                    ->label('in 1 Monat')
                    ->action(fn (Model $record) => $record->update(['follow_up' => now()->addMonth()])),
            ]);
    }
}
