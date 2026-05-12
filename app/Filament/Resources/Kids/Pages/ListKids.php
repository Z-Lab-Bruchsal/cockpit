<?php

namespace App\Filament\Resources\Kids\Pages;

use App\Filament\Resources\Kids\KidResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKids extends ListRecords
{
    protected static string $resource = KidResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
