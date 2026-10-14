<?php

namespace App\Filament\Resources\CourseTemplates\Pages;

use App\Filament\Resources\CourseTemplates\CourseTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCourseTemplates extends ListRecords
{
    protected static string $resource = CourseTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
