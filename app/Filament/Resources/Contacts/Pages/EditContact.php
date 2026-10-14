<?php

namespace App\Filament\Resources\Contacts\Pages;

use App\Filament\Resources\Contacts\Actions\SendLoginLinkAction;
use App\Filament\Resources\Contacts\ContactResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContact extends EditRecord
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendLoginLinkAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $wantsInterestMails = (bool) ($data['wants_interest_mails'] ?? false);
        unset($data['wants_interest_mails']);

        $data['interest_mails_consent_at'] = $wantsInterestMails
            ? ($this->getRecord()->interest_mails_consent_at ?? now())
            : null;

        return $data;
    }
}
