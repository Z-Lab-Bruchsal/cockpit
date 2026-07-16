<?php

namespace App\Filament\Resources\TimeEntries\Pages;

use App\Filament\Resources\TimeEntries\TimeEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTimeEntry extends CreateRecord
{
    protected static string $resource = TimeEntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['recorded_by_user_id'] = filament()->auth()->user()->id;

        return $data;
    }
}
