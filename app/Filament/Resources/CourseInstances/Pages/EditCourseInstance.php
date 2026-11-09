<?php

namespace App\Filament\Resources\CourseInstances\Pages;

use App\Filament\Actions\HomepagePreviewAction;
use App\Filament\Resources\CourseInstances\Actions\NotifyInterestedAction;
use App\Filament\Resources\CourseInstances\CourseInstanceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCourseInstance extends EditRecord
{
    protected static string $resource = CourseInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            HomepagePreviewAction::make()->forCourseInstance(),
            NotifyInterestedAction::make(),
            DeleteAction::make(),
        ];
    }
}
