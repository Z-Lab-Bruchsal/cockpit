<?php

namespace App\Filament\Resources\Todos\Pages;

use App\Filament\Resources\Todos\TodoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTodo extends CreateRecord
{
    protected static string $resource = TodoResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = filament()->auth()->id();

        return $data;
    }
}
