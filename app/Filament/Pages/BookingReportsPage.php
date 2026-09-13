<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\Reports\BookingReportPdfService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Override;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class BookingReportsPage extends Page
{
    protected string $view = 'filament.pages.booking-reports-page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Zeiterfassung';

    protected static ?string $navigationLabel = 'Berichte';

    protected static ?string $title = 'Berichte';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    #[Override]
    public static function canAccess(): bool
    {
        return parent::canAccess() && User::find(filament()->auth()->user()->id)->can('View:BookingReportsPage');
    }

    public function mount(): void
    {
        $now = now();

        $this->form->fill([
            'user_id' => filament()->auth()->user()->id,
            'year' => $now->year,
            'month' => $now->month,
            'supervisor_year' => $now->year,
            'supervisor_month' => $now->month,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Eigener Bericht')
                    ->description('Stundennachweis für einen Monat oder eine Jahresübersicht.')
                    ->schema([
                        Select::make('user_id')
                            ->label('Benutzer')
                            ->options(fn () => $this->canViewForeign()
                                ? User::query()->orderBy('name')->pluck('name', 'id')
                                : User::query()->where('id', filament()->auth()->user()->id)->pluck('name', 'id'))
                            ->disabled(fn () => ! $this->canViewForeign())
                            ->searchable()
                            ->required(),
                        Select::make('year')
                            ->label('Jahr')
                            ->options($this->yearOptions())
                            ->required(),
                        Select::make('month')
                            ->label('Monat')
                            ->options(BookingReportPdfService::MONTH_NAMES)
                            ->required(),
                    ])
                    ->columns(3),
                Section::make('Alle Benutzer')
                    ->description('Übersicht über die Stunden aller Benutzer.')
                    ->visible(fn () => $this->canViewSupervisorReport())
                    ->schema([
                        Select::make('supervisor_year')
                            ->label('Jahr')
                            ->options($this->yearOptions())
                            ->required(),
                        Select::make('supervisor_month')
                            ->label('Monat')
                            ->options(BookingReportPdfService::MONTH_NAMES)
                            ->required(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function canViewForeign(): bool
    {
        return User::find(filament()->auth()->user()->id)->can('Worktimes:ViewForeign');
    }

    public function canViewSupervisorReport(): bool
    {
        return User::find(filament()->auth()->user()->id)->can('Worktimes:SupervisorReport');
    }

    public function generateMonthlyReportAction(): Action
    {
        return Action::make('generateMonthlyReport')
            ->label('Monatsbericht (PDF)')
            ->action(function (BookingReportPdfService $pdfService): StreamedResponse {
                $state = $this->form->getState();

                return $pdfService->monthlyDetail($this->resolveUser($state['user_id'] ?? null), (int) $state['year'], (int) $state['month']);
            });
    }

    public function generateYearlyReportAction(): Action
    {
        return Action::make('generateYearlyReport')
            ->label('Jahresbericht (PDF)')
            ->action(function (BookingReportPdfService $pdfService): StreamedResponse {
                $state = $this->form->getState();

                return $pdfService->yearlySummary($this->resolveUser($state['user_id'] ?? null), (int) $state['year']);
            });
    }

    public function generateSupervisorMonthlyReportAction(): Action
    {
        return Action::make('generateSupervisorMonthlyReport')
            ->label('Monatsübersicht – alle Benutzer (PDF)')
            ->visible(fn () => $this->canViewSupervisorReport())
            ->action(function (BookingReportPdfService $pdfService): StreamedResponse {
                $state = $this->form->getState();

                return $pdfService->supervisorMonthly((int) $state['supervisor_year'], (int) $state['supervisor_month']);
            });
    }

    public function generateSupervisorYearlyReportAction(): Action
    {
        return Action::make('generateSupervisorYearlyReport')
            ->label('Jahresübersicht – alle Benutzer (PDF)')
            ->visible(fn () => $this->canViewSupervisorReport())
            ->action(function (BookingReportPdfService $pdfService): StreamedResponse {
                $state = $this->form->getState();

                return $pdfService->supervisorYearly((int) $state['supervisor_year']);
            });
    }

    private function resolveUser(?int $userId): User
    {
        if (! $this->canViewForeign() || $userId === null) {
            return filament()->auth()->user();
        }

        return User::findOrFail($userId);
    }

    /**
     * @return array<int, int>
     */
    private function yearOptions(): array
    {
        $currentYear = (int) Carbon::now()->year;

        return array_combine(
            range($currentYear, $currentYear - 5),
            range($currentYear, $currentYear - 5),
        );
    }
}
