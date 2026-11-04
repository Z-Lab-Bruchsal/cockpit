<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Actions\HomepagePreviewAction;
use App\Filament\Resources\Courses\CourseResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCourse extends EditRecord
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            HomepagePreviewAction::make(),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
