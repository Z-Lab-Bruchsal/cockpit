<?php

namespace App\Services\Terminal;

use App\Enums\BookingSource;
use App\Enums\TerminalEventResult;
use App\Enums\TerminalEventType;
use App\Models\Booking;
use App\Models\Chip;
use App\Models\Terminal;
use App\Models\TerminalEvent;
use App\Models\User;
use App\Services\WorkTime\TimeClockService;
use App\Services\WorkTime\WorkTimeCalculator;
use Illuminate\Support\Carbon;

class TerminalBookingService
{
    /**
     * A second tap of the same user within this window does not toggle again.
     */
    public const DEBOUNCE_SECONDS = 60;

    public function __construct(
        private TimeClockService $timeClock,
        private WorkTimeCalculator $calculator,
    ) {}

    public function book(Terminal $terminal, string $chipUid): TerminalResponse
    {
        $chip = $this->registerSighting($terminal, $chipUid);

        if ($rejection = $this->rejectUnusableChip($chip)) {
            return $this->log($terminal, $chip, TerminalEventType::Book, $rejection);
        }

        $user = $chip->user;
        $lastTransitionAt = $this->timeClock->lastTransitionAt($user);

        if ($lastTransitionAt && $lastTransitionAt->greaterThan(now()->subSeconds(self::DEBOUNCE_SECONDS))) {
            return $this->log($terminal, $chip, TerminalEventType::Book, new TerminalResponse(
                'warning',
                'Bereits gestempelt',
                'Letzte Buchung um '.$this->formatTime($lastTransitionAt),
                TerminalEventResult::Debounced,
            ));
        }

        $booking = $this->timeClock->toggle($user, null, null, BookingSource::Terminal, $terminal);
        $isClockOut = $booking->end_at !== null;

        $response = $isClockOut
            ? new TerminalResponse(
                'ok',
                'Tschüss '.$user->name,
                'Gehen '.$this->formatTime($booking->end_at).' · heute '.$this->formatDuration($this->minutesToday($user)),
                TerminalEventResult::ClockOut,
            )
            : new TerminalResponse(
                'ok',
                'Hallo '.$user->name,
                'Kommen '.$this->formatTime($booking->start_at).' · heute '.$this->formatDuration($this->minutesToday($user)),
                TerminalEventResult::ClockIn,
            );

        return $this->log($terminal, $chip, TerminalEventType::Book, $response, $booking);
    }

    public function info(Terminal $terminal, string $chipUid): TerminalResponse
    {
        $chip = $this->registerSighting($terminal, $chipUid);

        if ($rejection = $this->rejectUnusableChip($chip)) {
            return $this->log($terminal, $chip, TerminalEventType::Info, $rejection);
        }

        $user = $chip->user;
        $currentSegmentMinutes = $this->calculator->currentSegmentMinutes($user);
        $today = 'heute '.$this->formatDuration($this->minutesToday($user));

        $text = $currentSegmentMinutes === null
            ? 'Nicht eingestempelt · '.$today
            : 'Anwesend seit '.$this->formatTime(now()->subMinutes($currentSegmentMinutes)).' ('.$this->formatDuration($currentSegmentMinutes).') · '.$today;

        return $this->log($terminal, $chip, TerminalEventType::Info, new TerminalResponse(
            'ok',
            $user->name,
            $text,
            TerminalEventResult::Info,
        ));
    }

    /**
     * Find or create the chip and remember where and when it was last seen, so
     * unknown chips show up in the cockpit for assignment.
     */
    private function registerSighting(Terminal $terminal, string $chipUid): Chip
    {
        $chip = Chip::firstOrCreate(
            ['uid' => Chip::normalizeUid($chipUid)],
            ['first_seen_at' => now()],
        );

        $chip->update([
            'last_seen_at' => now(),
            'last_seen_terminal_id' => $terminal->id,
        ]);

        return $chip;
    }

    private function rejectUnusableChip(Chip $chip): ?TerminalResponse
    {
        if (! $chip->user_id) {
            return new TerminalResponse('error', 'Chip unbekannt', 'Bitte im Cockpit zuordnen', TerminalEventResult::UnknownChip);
        }

        if (! $chip->is_active) {
            return new TerminalResponse('error', 'Chip gesperrt', 'Bitte an die Verwaltung wenden', TerminalEventResult::InactiveChip);
        }

        return null;
    }

    private function log(
        Terminal $terminal,
        Chip $chip,
        TerminalEventType $type,
        TerminalResponse $response,
        ?Booking $booking = null,
    ): TerminalResponse {
        TerminalEvent::create([
            'terminal_id' => $terminal->id,
            'chip_uid' => $chip->uid,
            'chip_id' => $chip->id,
            'user_id' => $chip->user_id,
            'type' => $type,
            'result' => $response->result,
            'booking_id' => $booking?->id,
        ]);

        return $response;
    }

    /**
     * Closed bookings of today plus the currently running one.
     */
    private function minutesToday(User $user): int
    {
        $today = Carbon::now(config('app.business_timezone'))->startOfDay();

        return $this->calculator->workedMinutes($user, $today)
            + ($this->calculator->currentSegmentMinutes($user) ?? 0);
    }

    private function formatTime(Carbon $time): string
    {
        return $time->copy()->setTimezone(config('app.business_timezone'))->format('H:i');
    }

    private function formatDuration(int $minutes): string
    {
        return sprintf('%d:%02d h', intdiv($minutes, 60), $minutes % 60);
    }
}
