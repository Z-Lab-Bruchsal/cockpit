<?php

namespace App\Filament\Resources\Kids\Pages;

use App\Filament\Resources\Kids\KidResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditKid extends EditRecord
{
    protected static string $resource = KidResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
