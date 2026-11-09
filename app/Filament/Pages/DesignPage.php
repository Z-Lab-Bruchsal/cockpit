<?php

namespace App\Filament\Pages;

use App\Filament\Forms\HexColorPicker;
use App\Models\DesignTheme;
use App\Models\DesignThemeRevision;
use App\Models\User;
use App\Services\Homepage\DesignRoles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Override;
use UnitEnum;

/**
 * Homepage colors per role and per single element, with the history of published versions.
 * The same colors can be changed directly on the homepage in the design mode (?design=1).
 *
 * @property-read Schema $form
 */
class DesignPage extends Page
{
    protected string $view = 'filament.pages.company-settings-page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Design';

    protected static ?string $title = 'Design der Homepage';

    protected static ?string $slug = 'design';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    #[Override]
    public static function canAccess(): bool
    {
        return parent::canAccess() && (bool) User::find(filament()->auth()->id())?->can('Homepage:Design');
    }

    public function mount(): void
    {
        $this->fillForm();
    }

    protected function fillForm(): void
    {
        $published = DesignTheme::current()->published();
        $elements = [];

        foreach ($published['elements'] as $elementId => $roles) {
            foreach ($roles as $role => $color) {
                $elements[] = ['element' => $elementId, 'role' => $role, 'color' => $color];
            }
        }

        $this->form->fill(['tokens' => $published['tokens'], 'elements' => $elements]);
    }

    public function form(Schema $schema): Schema
    {
        $theme = DesignTheme::current();

        $sections = [];

        foreach (DesignRoles::grouped() as $group => $roles) {
            $sections[] = Section::make($group)
                ->schema(collect($roles)->map(fn (string $label, string $role) => HexColorPicker::make($role)
                    ->label($label)
                    ->placeholder(DesignRoles::ROLES[$role]['default'])
                    ->helperText('Leer = Standard')
                    ->required(array_key_exists($role, DesignRoles::COMPANY_SETTING_ROLES)))
                    ->values()
                    ->all())
                ->columns(3)
                ->statePath('tokens')
                ->columnSpanFull();
        }

        return $schema
            ->components([
                Callout::make('Es gibt einen unveröffentlichten Entwurf aus dem Design-Modus.')
                    ->description('Er stammt von '.($theme->draftUser?->name ?? 'unbekannt').'. Speichern hier überschreibt die veröffentlichten Farben, der Entwurf bleibt bis zum Veröffentlichen oder Verwerfen im Design-Modus erhalten.')
                    ->warning()
                    ->visible($theme->draft !== null)
                    ->columnSpanFull(),
                ...$sections,
                Section::make('Farben einzelner Elemente')
                    ->description('Entstehen im Design-Modus mit „nur dieses Element“. Löschen setzt das Element auf die allgemeine Farbe zurück.')
                    ->schema([
                        Repeater::make('elements')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('element')
                                    ->label('Element')
                                    ->required()
                                    ->regex(DesignTheme::ELEMENT_ID_PATTERN)
                                    ->readOnly(),
                                Select::make('role')
                                    ->label('Farbe für')
                                    ->options(collect(DesignRoles::ROLES)->map(fn (array $role): string => $role['label'])->all())
                                    ->required(),
                                HexColorPicker::make('color')
                                    ->label('Farbe')
                                    ->required(),
                            ])
                            ->columns(3)
                            ->addable(false)
                            ->reorderable(false)
                            ->defaultItems(0),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $elements = [];

        foreach ($data['elements'] ?? [] as $row) {
            $elements[$row['element']][$row['role']] = $row['color'];
        }

        DesignTheme::current()->publish($this->user(), [
            'tokens' => array_filter($data['tokens'] ?? []),
            'elements' => $elements,
        ], 'Im Cockpit gespeichert');

        Notification::make()
            ->success()
            ->title('Farben veröffentlicht')
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('designOnHomepage')
                ->label('Auf der Homepage gestalten')
                ->icon(Heroicon::OutlinedPaintBrush)
                ->color('gray')
                ->url(route('homepage.index', ['design' => 1]))
                ->openUrlInNewTab(),
            Action::make('restore')
                ->label('Frühere Version')
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->schema([
                    Select::make('revision')
                        ->label('Version')
                        ->options(fn (): array => DesignThemeRevision::query()
                            ->with('user')
                            ->latest('id')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (DesignThemeRevision $revision): array => [
                                $revision->id => $revision->created_at->format('d.m.Y H:i').' – vor Änderung durch '.($revision->user?->name ?? 'unbekannt').($revision->note ? ' ('.$revision->note.')' : ''),
                            ])
                            ->all())
                        ->required(),
                ])
                ->modalDescription('Die gewählte Version wird wieder veröffentlicht. Der aktuelle Stand bleibt als Version erhalten.')
                ->action(function (array $data): void {
                    DesignTheme::current()->restore(DesignThemeRevision::query()->findOrFail($data['revision']), $this->user());
                    $this->fillForm();

                    Notification::make()->success()->title('Version wiederhergestellt')->send();
                }),
            Action::make('discardDraft')
                ->label('Entwurf verwerfen')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => DesignTheme::current()->draft !== null)
                ->action(function (): void {
                    DesignTheme::current()->discardDraft();

                    Notification::make()->success()->title('Entwurf verworfen')->send();
                }),
            Action::make('save')
                ->label('Speichern und veröffentlichen')
                ->keyBindings(['mod+s'])
                ->action(fn () => $this->save()),
        ];
    }

    protected function user(): User
    {
        /** @var User $user */
        $user = filament()->auth()->user();

        return $user;
    }
}
