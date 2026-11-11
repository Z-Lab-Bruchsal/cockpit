<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Analytics\AnalyticsFilterForm;
use App\Filament\Widgets\Analytics\CourseStatsWidget;
use App\Filament\Widgets\Analytics\EnrollmentsPerMonthChartWidget;
use App\Filament\Widgets\Analytics\InterestChartWidget;
use App\Filament\Widgets\Analytics\KidAgeChartWidget;
use App\Filament\Widgets\Analytics\OrderCostsByMerchantChartWidget;
use App\Filament\Widgets\Analytics\OrderCostsPerMonthChartWidget;
use App\Filament\Widgets\Analytics\OrderCostStatsWidget;
use App\Filament\Widgets\Analytics\ParticipantStatsWidget;
use App\Filament\Widgets\Analytics\TopCoursesWidget;
use App\Filament\Widgets\Analytics\TopKidsWidget;
use App\Filament\Widgets\Analytics\WorkHoursPerWeekChartWidget;
use App\Filament\Widgets\Analytics\WorkHoursWidget;
use App\Models\User;
use App\Services\Reports\AnalyticsFilters;
use App\Services\Reports\AnalyticsReportPdfService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Override;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * All analytics widgets on one page with a shared filter form; printable and downloadable as PDF.
 */
class AnalyticsPage extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'auswertungen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Auswertungen';

    protected static ?string $title = 'Auswertungen';

    #[Override]
    public static function canAccess(): bool
    {
        return parent::canAccess() && (bool) User::find(filament()->auth()->id())?->can('View:AnalyticsPage');
    }

    /**
     * @return list<class-string<Widget>>
     */
    #[Override]
    public function getWidgets(): array
    {
        return [
            CourseStatsWidget::class,
            ParticipantStatsWidget::class,
            KidAgeChartWidget::class,
            EnrollmentsPerMonthChartWidget::class,
            InterestChartWidget::class,
            TopCoursesWidget::class,
            TopKidsWidget::class,
            OrderCostStatsWidget::class,
            OrderCostsPerMonthChartWidget::class,
            OrderCostsByMerchantChartWidget::class,
            WorkHoursWidget::class,
            WorkHoursPerWeekChartWidget::class,
        ];
    }

    #[Override]
    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Filter')
                    ->icon(Heroicon::OutlinedFunnel)
                    ->collapsible()
                    ->compact()
                    ->extraAttributes(['class' => 'print:hidden'])
                    ->columns(AnalyticsFilterForm::columns())
                    ->schema(AnalyticsFilterForm::components()),
            ]);
    }

    #[Override]
    public function getFiltersFormContentComponent(): Component
    {
        return EmbeddedSchema::make('filtersForm');
    }

    #[Override]
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Text::make(fn (): string => AnalyticsFilters::fromArray($this->filters)->describe())
                    ->extraAttributes(['class' => 'hidden print:block']),
                $this->getFiltersFormContentComponent(),
                $this->getWidgetsContentComponent(),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Drucken')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->alpineClickHandler('window.print()'),
            Action::make('pdf')
                ->label('PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->modalHeading('Auswertung als PDF')
                ->modalSubmitActionLabel('Herunterladen')
                ->modalWidth('lg')
                ->schema([
                    CheckboxList::make('sections')
                        ->label('Abschnitte')
                        ->options(fn (AnalyticsReportPdfService $reports): array => $reports->sectionOptions(User::find(filament()->auth()->id())))
                        ->default(fn (AnalyticsReportPdfService $reports): array => array_keys($reports->sectionOptions(User::find(filament()->auth()->id()))))
                        ->bulkToggleable()
                        ->columns(2)
                        ->required(),
                ])
                ->action(function (array $data, AnalyticsReportPdfService $reports): StreamedResponse {
                    $filters = AnalyticsFilters::fromArray($this->filters);
                    $user = User::find(filament()->auth()->id());
                    $sections = array_values(array_intersect($data['sections'] ?? [], array_keys($reports->sectionOptions($user))));

                    return response()->streamDownload(
                        fn () => print ($reports->pdf($filters, $sections)),
                        $reports->filename($filters),
                        ['Content-Type' => 'application/pdf'],
                    );
                }),
        ];
    }
}
