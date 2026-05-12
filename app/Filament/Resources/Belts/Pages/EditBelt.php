<?php

namespace App\Filament\Resources\Belts\Pages;

use App\Filament\Resources\Belts\BeltResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBelt extends EditRecord
{
    protected static string $resource = BeltResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
