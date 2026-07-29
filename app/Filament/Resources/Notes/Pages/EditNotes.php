<?php

namespace App\Filament\Resources\Notes\Pages;

use App\Filament\Resources\Notes\NotesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNotes extends EditRecord
{
    protected static string $resource = NotesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
