<?php

namespace App\Filament\Resources\Registrations\Pages;

use App\Filament\Resources\Registrations\Actions\AddInstanceNoteAction;
use App\Filament\Resources\Registrations\Actions\ApproveRegistrationAction;
use App\Filament\Resources\Registrations\Actions\ApproveSchoolRequestAction;
use App\Filament\Resources\Registrations\Actions\CancelRegistrationAction;
use App\Filament\Resources\Registrations\Actions\ConfirmRegistrationAction;
use App\Filament\Resources\Registrations\Actions\DeclineRegistrationAction;
use App\Filament\Resources\Registrations\Actions\DeclineSchoolRequestAction;
use App\Filament\Resources\Registrations\Actions\SnoozeStaffReminderAction;
use App\Filament\Resources\Registrations\RegistrationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewRegistration extends ViewRecord
{
    protected static string $resource = RegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ApproveSchoolRequestAction::make(),
            DeclineSchoolRequestAction::make(),
            SnoozeStaffReminderAction::make(),
            AddInstanceNoteAction::make(),
            ConfirmRegistrationAction::make(),
            ApproveRegistrationAction::make(),
            DeclineRegistrationAction::make(),
            CancelRegistrationAction::make(),
        ];
    }
}
