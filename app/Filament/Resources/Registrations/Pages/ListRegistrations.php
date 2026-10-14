<?php

namespace App\Filament\Resources\Registrations\Pages;

use App\Enums\RegistrationStatus;
use App\Filament\Resources\Registrations\RegistrationResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRegistrations extends ListRecords
{
    protected static string $resource = RegistrationResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Aktiv')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [RegistrationStatus::Confirmed, RegistrationStatus::PendingConfirmation, RegistrationStatus::Requested, RegistrationStatus::AwaitingApproval])),
            'approval' => Tab::make('Freigabe nötig')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where(fn (Builder $query) => $query
                    ->whereIn('status', [RegistrationStatus::AwaitingApproval, RegistrationStatus::Requested])
                    ->orWhere(fn (Builder $waitlist) => $waitlist->whereNotNull('approval_requested_at')->whereNull('approval_decided_at')))),
            'schools' => Tab::make('Schulklassen')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNotNull('school_id')->where('status', '!=', RegistrationStatus::Held)),
            'pending' => Tab::make('Warten auf Bestätigung')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RegistrationStatus::PendingConfirmation)),
            'all' => Tab::make('Alle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', '!=', RegistrationStatus::Held)),
        ];
    }
}
