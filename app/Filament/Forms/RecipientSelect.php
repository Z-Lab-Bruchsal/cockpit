<?php

namespace App\Filament\Forms;

use App\Services\Recipients\Recipients;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;

/**
 * Searchable multi-select offering users and groups at once.
 *
 * The record has to provide recipientKeys(): list<string> and syncRecipients(list<string>): void.
 */
class RecipientSelect extends Select
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Empfänger')
            ->multiple()
            ->searchable()
            ->options(fn (): array => Recipients::options())
            ->placeholder('Personen oder Gruppen suchen …')
            ->loadStateFromRelationshipsUsing(function (RecipientSelect $component, Model $record): void {
                $component->state($record->recipientKeys());
            })
            ->saveRelationshipsUsing(function (Model $record, ?array $state): void {
                $record->syncRecipients($state ?? []);
            })
            ->dehydrated(false);
    }
}
