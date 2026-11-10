<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\Reports\OrderReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Override;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Report of placed orders for accounting: shows the orders of the period, downloads them as Excel, CSV or PDF.
 * The weekly mail is set up under Einstellungen → Wöchentlicher Bericht.
 *
 * @property-read Schema $form
 */
class OrderReportsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.order-reports-page';

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
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetTable()),
                        DatePicker::make('to')
                            ->label('Bis')
                            ->required()
                            ->afterOrEqual('from')
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetTable()),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => OrderReportService::query(...$this->period()))
            ->defaultSort('orderdatetime')
            ->paginated([25, 50, 100, 'all'])
            ->emptyStateHeading('Keine Bestellungen in diesem Zeitraum')
            ->columns([
                TextColumn::make('orderdatetime')
                    ->label('Bestellt am')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('merchant.name')
                    ->label('Händler')
                    ->placeholder('Ohne Händler')
                    ->sortable(),
                TextColumn::make('order_number')
                    ->label('Bestellnummer')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Artikel')
                    ->searchable()
                    ->url(fn (Order $record): string => OrderResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('count')
                    ->label('Anzahl')
                    ->numeric(),
                TextColumn::make('total_price')
                    ->label('Preis')
                    ->money('EUR', locale: 'de')
                    ->placeholder('fehlt')
                    ->sortable()
                    ->summarize(Sum::make()->label('Summe')->money('EUR', locale: 'de')),
                TextColumn::make('user.name')
                    ->label('Angefragt von')
                    ->toggleable(),
                TextColumn::make('processedBy.name')
                    ->label('Bestellt von')
                    ->toggleable(),
            ]);
    }

    /**
     * Selected days as [start of the first day, start of the day after the last day]; invalid input falls back to last week.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function period(): array
    {
        $from = rescue(fn (): Carbon => Carbon::parse($this->data['from'] ?? null), now()->subWeek()->startOfWeek(), report: false);
        $to = rescue(fn (): Carbon => Carbon::parse($this->data['to'] ?? null), now()->subWeek()->endOfWeek(), report: false);

        return OrderReportService::forDays($from, $to->max($from));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('orders')
                ->label('Bestellungen')
                ->icon(Heroicon::OutlinedShoppingBag)
                ->color('gray')
                ->url(fn (): string => OrderResource::getUrl()),
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
            ->modalHeading($label.' herunterladen')
            ->modalSubmitActionLabel('Herunterladen')
            ->modalWidth('lg')
            ->schema([
                CheckboxList::make('columns')
                    ->label('Spalten')
                    ->options(OrderReportService::columnOptions())
                    ->default(OrderReportService::defaultColumns())
                    ->bulkToggleable()
                    ->columns(2)
                    ->required(),
            ])
            ->action(function (array $data, OrderReportService $reports) use ($format): BinaryFileResponse|StreamedResponse {
                $state = $this->form->getState();
                [$from, $to] = OrderReportService::forDays(Carbon::parse($state['from']), Carbon::parse($state['to']));
                $filename = $reports->filename($from, $to, $format);
                $columns = $data['columns'];

                return match ($format) {
                    'xlsx' => response()->download($reports->xlsx($from, $to, $columns), $filename)->deleteFileAfterSend(),
                    'csv' => response()->streamDownload(fn () => print ($reports->csv($from, $to, $columns)), $filename, ['Content-Type' => 'text/csv; charset=UTF-8']),
                    default => response()->streamDownload(fn () => print ($reports->pdf($from, $to, $columns)), $filename, ['Content-Type' => 'application/pdf']),
                };
            });
    }
}
