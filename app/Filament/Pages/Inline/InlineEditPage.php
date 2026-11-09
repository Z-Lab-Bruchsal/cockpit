<?php

namespace App\Filament\Pages\Inline;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * Base of the small cockpit pages that are opened in a dialog on the homepage to edit content in place.
 * They have no navigation and tell the homepage via postMessage when they are done, so it can reload.
 *
 * @property-read Schema $form
 */
abstract class InlineEditPage extends Page
{
    protected static string $layout = 'filament-panels::components.layout.base';

    protected string $view = 'filament.pages.inline-edit';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    abstract protected function persist(array $data): void;

    public function save(): void
    {
        $this->persist($this->form->getState());

        Notification::make()
            ->success()
            ->title('Gespeichert')
            ->send();

        $this->js("window.parent.postMessage({ type: 'zlab-inline-saved' }, window.location.origin)");
    }

    public function cancel(): void
    {
        $this->js("window.parent.postMessage({ type: 'zlab-inline-closed' }, window.location.origin)");
    }
}
