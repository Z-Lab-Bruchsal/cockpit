<?php

namespace App\Filament\Resources\Contacts\Pages;

use App\Enums\ContactType;
use App\Filament\Resources\Contacts\ContactResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListContacts extends ListRecords
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Alle'),
            'parents' => Tab::make('Eltern')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('type', ContactType::Parent)),
            'schools' => Tab::make('Schulen')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('type', ContactType::School)),
        ];
    }
}
