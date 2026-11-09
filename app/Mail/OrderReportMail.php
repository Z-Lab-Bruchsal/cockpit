<?php

namespace App\Mail;

use App\Services\Reports\OrderReportService;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Weekly report of placed orders for accounting, with Excel and PDF attached.
 */
class OrderReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CarbonInterface $periodStart,
        public CarbonInterface $periodEnd,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bestellungen '.$this->periodStart->format('d.m.').' – '.$this->periodEnd->copy()->subSecond()->format('d.m.Y'),
        );
    }

    public function content(): Content
    {
        $reports = app(OrderReportService::class);
        $orders = $reports->orders($this->periodStart, $this->periodEnd);

        return new Content(
            markdown: 'mail.order-report',
            with: [
                'from' => $this->periodStart,
                'to' => $this->periodEnd,
                'count' => $orders->count(),
                'totals' => $reports->totalsByMerchant($orders),
                'total' => round((float) $orders->sum('total_price'), 2),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $reports = app(OrderReportService::class);

        return [
            Attachment::fromData(function () use ($reports): string {
                $path = $reports->xlsx($this->periodStart, $this->periodEnd);
                $data = (string) file_get_contents($path);
                unlink($path);

                return $data;
            }, $reports->filename($this->periodStart, $this->periodEnd, 'xlsx'))
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            Attachment::fromData(fn (): string => $reports->pdf($this->periodStart, $this->periodEnd), $reports->filename($this->periodStart, $this->periodEnd, 'pdf'))
                ->withMime('application/pdf'),
        ];
    }
}
