<?php

namespace App\Filament\Resources\Sections\Pages;

use App\Filament\Actions\HomepagePreviewAction;
use App\Filament\Resources\Sections\SectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSection extends CreateRecord
{
    protected static string $resource = SectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            HomepagePreviewAction::make()->forSection(),
        ];
    }
}
