<?php

namespace App\Filament\Resources\CourseInstances\Pages;

use App\Filament\Resources\CourseInstances\CourseInstanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCourseInstances extends ListRecords
{
    protected static string $resource = CourseInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
