<?php

namespace App\Filament\Resources\TimeProfiles\Pages;

use App\Filament\Resources\TimeProfiles\TimeProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTimeProfiles extends ListRecords
{
    protected static string $resource = TimeProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
