<?php

namespace App\Filament\Resources\Kids\Pages;

use App\Filament\Resources\Kids\KidResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewKid extends ViewRecord
{
    protected static string $resource = KidResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
