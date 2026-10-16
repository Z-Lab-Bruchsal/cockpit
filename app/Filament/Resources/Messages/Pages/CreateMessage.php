<?php

namespace App\Filament\Resources\Messages\Pages;

use App\Filament\Resources\Messages\MessageResource;
use App\Models\Message;
use App\Services\Recipients\Recipients;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\Url;

class CreateMessage extends CreateRecord
{
    protected static string $resource = MessageResource::class;

    /**
     * Id of the message being answered, prefills recipient and subject.
     */
    #[Url]
    public ?int $reply = null;

    protected function afterFill(): void
    {
        $original = $this->reply ? MessageResource::getEloquentQuery()->find($this->reply) : null;

        if (! $original instanceof Message) {
            return;
        }

        $this->form->fill([
            'recipients' => [Recipients::key(Recipients::USER, $original->user_id)],
            'subject' => str_starts_with($original->subject, 'Re: ') ? $original->subject : "Re: {$original->subject}",
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = filament()->auth()->id();

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Nachricht gesendet';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index', ['tab' => 'sent']);
    }
}
