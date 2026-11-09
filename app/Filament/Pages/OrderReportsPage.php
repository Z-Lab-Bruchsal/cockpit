<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\Reports\OrderReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Override;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Report of placed orders for accounting, as Excel, CSV or PDF. The weekly mail is set up under Einstellungen → Wöchentlicher Bericht.
 *
 * @property-read Schema $form
 */
class OrderReportsPage extends Page
{
    protected string $view = 'filament.pages.company-settings-page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Bestellbericht';

    protected static ?string $title = 'Bestellbericht';

    protected static ?string $slug = 'bestellbericht';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    #[Override]
    public static function canAccess(): bool
    {
        return parent::canAccess() && (bool) User::find(filament()->auth()->id())?->can('Orders:Export');
    }

    public function mount(): void
    {
        $this->form->fill([
            'from' => now()->subWeek()->startOfWeek()->toDateString(),
            'to' => now()->subWeek()->endOfWeek()->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Zeitraum')
                    ->description('Alle Bestellungen, die in diesem Zeitraum bestellt wurden (Datum „Bestellt am“).')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Von')
                            ->required(),
                        DatePicker::make('to')
                            ->label('Bis')
                            ->required()
                            ->afterOrEqual('from'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->downloadAction('xlsx', 'Excel'),
            $this->downloadAction('csv', 'CSV'),
            $this->downloadAction('pdf', 'PDF'),
        ];
    }

    protected function downloadAction(string $format, string $label): Action
    {
        return Action::make('download_'.$format)
            ->label($label)
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color($format === 'pdf' ? 'primary' : 'gray')
            ->action(function (OrderReportService $reports) use ($format): BinaryFileResponse|StreamedResponse {
                $state = $this->form->getState();
                [$from, $to] = OrderReportService::forDays(Carbon::parse($state['from']), Carbon::parse($state['to']));
                $filename = $reports->filename($from, $to, $format);

                return match ($format) {
                    'xlsx' => response()->download($reports->xlsx($from, $to), $filename)->deleteFileAfterSend(),
                    'csv' => response()->streamDownload(fn () => print ($reports->csv($from, $to)), $filename, ['Content-Type' => 'text/csv; charset=UTF-8']),
                    default => response()->streamDownload(fn () => print ($reports->pdf($from, $to)), $filename, ['Content-Type' => 'application/pdf']),
                };
            });
    }
}
