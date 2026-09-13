<?php

namespace App\Services\Reports;

use App\Models\CompanySetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingReportPdfService
{
    /**
     * @var array<int, string>
     */
    public const MONTH_NAMES = [
        1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
        5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
    ];

    public function __construct(private readonly BookingReportService $reports) {}

    public function monthlyDetail(User $user, int $year, int $month): StreamedResponse
    {
        $bookings = $this->reports->monthlyBookings($user, $year, $month);

        $pdf = Pdf::loadView('pdf.bookings.monthly-detail', [
            'company' => CompanySetting::current(),
            'recipient' => $user,
            'user' => $user,
            'year' => $year,
            'monthLabel' => self::MONTH_NAMES[$month],
            'bookings' => $bookings,
            'totalMinutes' => (int) $bookings->sum('worked_minutes'),
            'timezone' => config('app.business_timezone'),
            'formatMinutes' => static fn (?int $minutes) => BookingReportService::formatMinutes($minutes),
            'generatedAt' => now(),
        ]);

        return $this->stream($pdf, $this->filename($user->name, "stundennachweis-{$year}-".str_pad((string) $month, 2, '0', STR_PAD_LEFT)));
    }

    public function yearlySummary(User $user, int $year): StreamedResponse
    {
        $monthlyTotals = $this->reports->yearlyMonthlyTotals($user, $year);

        $pdf = Pdf::loadView('pdf.bookings.yearly-summary', [
            'company' => CompanySetting::current(),
            'recipient' => $user,
            'user' => $user,
            'year' => $year,
            'monthlyTotals' => $monthlyTotals,
            'monthNames' => self::MONTH_NAMES,
            'totalMinutes' => array_sum($monthlyTotals),
            'formatMinutes' => static fn (?int $minutes) => BookingReportService::formatMinutes($minutes),
            'generatedAt' => now(),
        ]);

        return $this->stream($pdf, $this->filename($user->name, "jahresuebersicht-{$year}"));
    }

    public function supervisorMonthly(int $year, int $month): StreamedResponse
    {
        $totals = $this->reports->monthlyTotalsForAllUsers($year, $month);

        $pdf = Pdf::loadView('pdf.bookings.supervisor-summary', [
            'company' => CompanySetting::current(),
            'title' => 'Monatsübersicht – alle Benutzer',
            'periodLabel' => self::MONTH_NAMES[$month].' '.$year,
            'totals' => $totals,
            'formatMinutes' => static fn (?int $minutes) => BookingReportService::formatMinutes($minutes),
            'generatedAt' => now(),
        ]);

        return $this->stream($pdf, $this->filename('alle-benutzer', "monatsuebersicht-{$year}-".str_pad((string) $month, 2, '0', STR_PAD_LEFT)));
    }

    public function supervisorYearly(int $year): StreamedResponse
    {
        $totals = $this->reports->yearlyTotalsForAllUsers($year);

        $pdf = Pdf::loadView('pdf.bookings.supervisor-summary', [
            'company' => CompanySetting::current(),
            'title' => 'Jahresübersicht – alle Benutzer',
            'periodLabel' => (string) $year,
            'totals' => $totals,
            'formatMinutes' => static fn (?int $minutes) => BookingReportService::formatMinutes($minutes),
            'generatedAt' => now(),
        ]);

        return $this->stream($pdf, $this->filename('alle-benutzer', "jahresuebersicht-{$year}"));
    }

    private function stream(PdfDocument $pdf, string $filename): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function filename(string $subject, string $suffix): string
    {
        return Str::slug($subject).'-'.$suffix.'.pdf';
    }
}
