<?php

namespace App\Services\Reports;

use App\Filament\Widgets\Analytics\CourseStatsWidget;
use App\Filament\Widgets\Analytics\EnrollmentsPerMonthChartWidget;
use App\Filament\Widgets\Analytics\InterestChartWidget;
use App\Filament\Widgets\Analytics\KidAgeChartWidget;
use App\Filament\Widgets\Analytics\OrderCostStatsWidget;
use App\Filament\Widgets\Analytics\ParticipantStatsWidget;
use App\Filament\Widgets\Analytics\TopCoursesWidget;
use App\Filament\Widgets\Analytics\TopKidsWidget;
use App\Filament\Widgets\Analytics\WorkHoursWidget;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Number;

/**
 * The analytics as PDF: figures, distributions as simple bars and the top lists.
 */
class AnalyticsReportPdfService
{
    public function __construct(protected AnalyticsService $analytics) {}

    /**
     * Sections in their fixed order with the widget whose permission they require.
     *
     * @return array<string, array{label: string, widget: class-string}>
     */
    public static function sections(): array
    {
        return [
            'courses' => ['label' => 'Kurse', 'widget' => CourseStatsWidget::class],
            'participants' => ['label' => 'Teilnehmende', 'widget' => ParticipantStatsWidget::class],
            'ages' => ['label' => 'Alter der Kinder', 'widget' => KidAgeChartWidget::class],
            'months' => ['label' => 'Anmeldungen pro Monat', 'widget' => EnrollmentsPerMonthChartWidget::class],
            'interests' => ['label' => 'Anmeldungen nach Interesse', 'widget' => InterestChartWidget::class],
            'top_courses' => ['label' => 'Top-Kurse', 'widget' => TopCoursesWidget::class],
            'top_kids' => ['label' => 'Top-Kinder', 'widget' => TopKidsWidget::class],
            'orders' => ['label' => 'Kosten aus Bestellungen', 'widget' => OrderCostStatsWidget::class],
            'work_hours' => ['label' => 'Stunden der Übungsleiter', 'widget' => WorkHoursWidget::class],
        ];
    }

    /**
     * Sections the user may download, as key => label.
     *
     * @return array<string, string>
     */
    public function sectionOptions(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $options = [];

        foreach (static::sections() as $key => $section) {
            if ($this->userMaySee($user, $key, $section['widget'])) {
                $options[$key] = $section['label'];
            }
        }

        return $options;
    }

    /**
     * @param  list<string>  $sections
     */
    public function pdf(AnalyticsFilters $filters, array $sections): string
    {
        $data = ['sections' => array_values(array_intersect(array_keys(static::sections()), $sections))];
        $selected = array_flip($data['sections']);

        if (isset($selected['courses'])) {
            $data['courses'] = $this->analytics->courseSummary($filters);
        }

        if (isset($selected['participants'])) {
            $data['participants'] = $this->analytics->participantSummary($filters);
        }

        if (isset($selected['ages'])) {
            $data['ages'] = $this->analytics->ageDistribution($filters);
        }

        if (isset($selected['months'])) {
            $data['months'] = $this->analytics->enrollmentsPerMonth($filters);
        }

        if (isset($selected['interests'])) {
            $data['interests'] = $this->analytics->enrollmentsPerInterest($filters);
        }

        if (isset($selected['top_courses'])) {
            $data['topCourses'] = $this->analytics->topCourses($filters);
        }

        if (isset($selected['top_kids'])) {
            $data['topKids'] = $this->analytics->topKids($filters);
        }

        if (isset($selected['orders'])) {
            $data['orders'] = $this->analytics->orderSummary($filters);
            $data['orderMonths'] = $this->analytics->orderCostsPerMonth($filters);
            $data['orderMerchants'] = $this->analytics->orderCostsPerMerchant($filters);
        }

        if (isset($selected['work_hours'])) {
            $data['workHours'] = $this->analytics->workHours($filters);
        }

        return Pdf::loadView('pdf.analytics.report', [
            ...$data,
            'company' => CompanySetting::current(),
            'filtersDescription' => $filters->describe(),
            'money' => static fn (?float $amount): string => $amount === null ? '–' : (string) Number::currency($amount, 'EUR', 'de'),
            'number' => static fn (int|float|null $value, int $precision = 0): string => $value === null ? '–' : (string) Number::format($value, precision: $precision, locale: 'de'),
            'duration' => static fn (int $minutes): string => BookingReportService::formatMinutes($minutes),
            'generatedAt' => now(config('app.business_timezone')),
        ])->setPaper('a4')->output();
    }

    public function filename(AnalyticsFilters $filters): string
    {
        return 'auswertung-'.$filters->from->format('Y-m-d').'-bis-'.$filters->until->format('Y-m-d').'.pdf';
    }

    /**
     * @param  class-string  $widget
     */
    protected function userMaySee(User $user, string $key, string $widget): bool
    {
        if (! $user->can('View:'.class_basename($widget))) {
            return false;
        }

        return match ($key) {
            'orders' => $user->can('viewAny', Order::class),
            'work_hours' => $user->can('Worktimes:SupervisorReport'),
            default => true,
        };
    }
}
