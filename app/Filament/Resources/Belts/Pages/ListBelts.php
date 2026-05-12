<?php

namespace App\Filament\Resources\Belts\Pages;

use App\Filament\Resources\Belts\BeltResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBelts extends ListRecords
{
    protected static string $resource = BeltResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
