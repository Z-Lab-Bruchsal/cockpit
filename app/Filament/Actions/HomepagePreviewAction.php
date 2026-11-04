<?php

namespace App\Filament\Actions;

use App\Services\Homepage\HomepagePreview;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

/**
 * Shows the current, possibly unsaved form state of a course or section as it would look on the homepage.
 */
class HomepagePreviewAction extends Action
{
    protected string $previewType = HomepagePreview::TYPE_COURSE;

    public static function getDefaultName(): ?string
    {
        return 'homepagePreview';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Vorschau')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalHeading('Vorschau auf der Homepage')
            ->modalDescription('Zeigt auch noch nicht gespeicherte Änderungen.')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Schließen')
            ->modalContent(fn (CreateRecord|EditRecord $livewire): View => view('filament.homepage-preview', [
                'tabs' => $this->tabs(HomepagePreview::store(
                    $this->previewType,
                    $livewire->form->getRawState(),
                    $livewire instanceof EditRecord ? $livewire->getRecord() : null,
                    (int) filament()->auth()->id(),
                )),
            ]));
    }

    public function forSection(): static
    {
        $this->previewType = HomepagePreview::TYPE_SECTION;

        return $this;
    }

    /**
     * @return array<string, string> Label => URL
     */
    protected function tabs(string $token): array
    {
        $labels = $this->previewType === HomepagePreview::TYPE_SECTION
            ? ['karte' => 'Kachel', 'seite' => 'Seite']
            : ['karte' => 'Visitenkarte', 'seite' => 'Detailseite'];

        return collect($labels)
            ->mapWithKeys(fn (string $label, string $variant): array => [$label => route('homepage.preview', ['token' => $token, 'variant' => $variant])])
            ->all();
    }
}
