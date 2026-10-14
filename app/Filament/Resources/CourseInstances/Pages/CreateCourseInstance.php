<?php

namespace App\Filament\Resources\CourseInstances\Pages;

use App\Enums\CourseInstanceStatus;
use App\Filament\Resources\CourseInstances\CourseInstanceResource;
use App\Models\CourseInstance;
use App\Services\Notifications\InterestNotificationService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCourseInstance extends CreateRecord
{
    protected static string $resource = CourseInstanceResource::class;

    /**
     * Runs after the dates were saved, so the age of the children is checked against the first date.
     */
    protected function afterCreate(): void
    {
        /** @var CourseInstance $instance */
        $instance = $this->getRecord()->refresh();

        if (! ($this->data['notify_interested'] ?? false) || $instance->status !== CourseInstanceStatus::Published || ! $instance->course->is_published) {
            return;
        }

        $service = app(InterestNotificationService::class);
        $filters = $service->defaultFilters($instance);
        $count = $service->notify($instance, $filters['interest_ids'], $filters['min_age'], $filters['max_age'], auth()->user());

        Notification::make()
            ->info()
            ->title($count === 1 ? 'Neuer Kurs für 1 Familie vorgemerkt' : "Neuer Kurs für {$count} Familien vorgemerkt")
            ->send();
    }
}
