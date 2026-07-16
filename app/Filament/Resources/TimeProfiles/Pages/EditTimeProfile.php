<?php

namespace App\Filament\Resources\TimeProfiles\Pages;

use App\Filament\Resources\TimeProfiles\TimeProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTimeProfile extends EditRecord
{
    protected static string $resource = TimeProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
