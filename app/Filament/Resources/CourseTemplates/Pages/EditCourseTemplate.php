<?php

namespace App\Filament\Resources\CourseTemplates\Pages;

use App\Filament\Resources\CourseTemplates\CourseTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCourseTemplate extends EditRecord
{
    protected static string $resource = CourseTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
