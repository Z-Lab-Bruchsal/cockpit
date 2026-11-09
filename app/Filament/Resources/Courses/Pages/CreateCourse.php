<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Actions\DraftCourseWithAiAction;
use App\Filament\Actions\HomepagePreviewAction;
use App\Filament\Resources\Courses\CourseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCourse extends CreateRecord
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DraftCourseWithAiAction::make(),
            HomepagePreviewAction::make(),
        ];
    }
}
