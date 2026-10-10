<?php

namespace Tests\Feature\TerminalApi;

use App\Enums\BookingSource;
use App\Enums\TerminalEventResult;
use App\Models\Booking;
use App\Models\Chip;
use App\Models\Terminal;
use App\Models\TerminalEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TerminalApiTest extends TestCase
{
    use RefreshDatabase;

    private Terminal $terminal;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-16 08:00:00', config('app.business_timezone')));

        $this->terminal = Terminal::factory()->create(['username' => 'term1', 'password' => 'secret']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function callTerminal(string $uri, string $username = 'term1', string $password = 'secret'): TestResponse
    {
        return $this->getJson($uri, [
            'Authorization' => 'Basic '.base64_encode("{$username}:{$password}"),
        ]);
    }

    public function test_request_without_credentials_is_rejected(): void
    {
        $this->getJson('/api/v1/book/04A1B2C3')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate');

        $this->assertDatabaseCount('chips', 0);
    }

    public function test_request_with_wrong_password_is_rejected(): void
    {
        $this->callTerminal('/api/v1/book/04A1B2C3', 'term1', 'wrong')->assertUnauthorized();
    }

    public function test_inactive_terminal_is_rejected(): void
    {
        Terminal::factory()->inactive()->create(['username' => 'term2', 'password' => 'secret']);

        $this->callTerminal('/api/v1/book/04A1B2C3', 'term2')->assertUnauthorized();
    }

    public function test_successful_request_updates_terminal_last_seen(): void
    {
        $this->callTerminal('/api/v1/bookinginfo/04A1B2C3')->assertOk();

        $this->assertTrue($this->terminal->fresh()->last_seen_at->equalTo(now()));
    }

    public function test_book_toggles_between_clock_in_and_clock_out(): void
    {
        $user = User::factory()->create(['name' => 'Max']);
        Chip::factory()->create(['uid' => '04A1B2C3', 'user_id' => $user->id]);

        $this->callTerminal('/api/v1/book/04A1B2C3')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'subject' => 'Hallo Max', 'text' => 'Kommen 08:00 · heute 0:00 h']);

        $booking = Booking::sole();
        $this->assertSame(BookingSource::Terminal, $booking->source);
        $this->assertSame($this->terminal->id, $booking->terminal_id);
        $this->assertNull($booking->recorded_by_user_id);
        $this->assertNull($booking->end_at);

        Carbon::setTestNow(now()->addMinutes(90));

        $this->callTerminal('/api/v1/book/04A1B2C3')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'subject' => 'Tschüss Max', 'text' => 'Gehen 09:30 · heute 1:30 h']);

        $this->assertSame(90, $booking->fresh()->worked_minutes);
        $this->assertSame(
            [TerminalEventResult::ClockIn, TerminalEventResult::ClockOut],
            TerminalEvent::orderBy('id')->pluck('result')->all(),
        );
        $this->assertSame($booking->id, TerminalEvent::first()->booking_id);
    }

    public function test_second_tap_within_debounce_window_does_not_toggle(): void
    {
        $user = User::factory()->create();
        Chip::factory()->create(['uid' => '04A1B2C3', 'user_id' => $user->id]);

        $this->callTerminal('/api/v1/book/04A1B2C3')->assertJson(['status' => 'ok']);

        Carbon::setTestNow(now()->addSeconds(30));

        $this->callTerminal('/api/v1/book/04A1B2C3')
            ->assertOk()
            ->assertExactJson(['status' => 'warning', 'subject' => 'Bereits gestempelt', 'text' => 'Letzte Buchung um 08:00']);

        $this->assertNull(Booking::sole()->end_at);
        $this->assertSame(TerminalEventResult::Debounced, TerminalEvent::latest('id')->first()->result);
    }

    public function test_tap_after_debounce_window_toggles_again(): void
    {
        $user = User::factory()->create();
        Chip::factory()->create(['uid' => '04A1B2C3', 'user_id' => $user->id]);

        $this->callTerminal('/api/v1/book/04A1B2C3');

        Carbon::setTestNow(now()->addSeconds(61));

        $this->callTerminal('/api/v1/book/04A1B2C3')->assertJson(['status' => 'ok', 'subject' => 'Tschüss '.$user->name]);

        $this->assertNotNull(Booking::sole()->end_at);
    }

    public function test_unknown_chip_is_registered_and_can_be_assigned_afterwards(): void
    {
        $this->callTerminal('/api/v1/book/04:a1:b2:c3')
            ->assertOk()
            ->assertExactJson(['status' => 'error', 'subject' => 'Chip unbekannt', 'text' => 'Bitte im Cockpit zuordnen']);

        $chip = Chip::sole();
        $this->assertSame('04A1B2C3', $chip->uid);
        $this->assertNull($chip->user_id);
        $this->assertSame($this->terminal->id, $chip->last_seen_terminal_id);
        $this->assertTrue($chip->first_seen_at->equalTo(now()));
        $this->assertDatabaseCount('bookings', 0);
        $this->assertSame(TerminalEventResult::UnknownChip, TerminalEvent::sole()->result);

        $user = User::factory()->create();
        $chip->update(['user_id' => $user->id]);

        $this->callTerminal('/api/v1/book/04A1B2C3')->assertJson(['status' => 'ok']);

        $this->assertSame($user->id, Booking::sole()->user_id);
    }

    public function test_inactive_chip_is_rejected(): void
    {
        Chip::factory()->inactive()->create(['uid' => '04A1B2C3']);

        $this->callTerminal('/api/v1/book/04A1B2C3')
            ->assertOk()
            ->assertJson(['status' => 'error', 'subject' => 'Chip gesperrt']);

        $this->assertDatabaseCount('bookings', 0);
        $this->assertSame(TerminalEventResult::InactiveChip, TerminalEvent::sole()->result);
    }

    public function test_uid_spellings_are_normalized_to_the_same_chip(): void
    {
        $user = User::factory()->create();
        Chip::factory()->create(['uid' => '04A1B2C3', 'user_id' => $user->id]);

        $this->callTerminal('/api/v1/bookinginfo/04a1b2c3')->assertJson(['subject' => $user->name]);
        $this->callTerminal('/api/v1/bookinginfo/04-A1-B2-C3')->assertJson(['subject' => $user->name]);

        $this->assertDatabaseCount('chips', 1);
    }

    public function test_invalid_chip_id_is_not_routed(): void
    {
        $this->callTerminal('/api/v1/book/not-a-chip')->assertNotFound();
    }

    public function test_booking_info_reports_status_without_changing_bookings(): void
    {
        $user = User::factory()->create(['name' => 'Max']);
        Chip::factory()->create(['uid' => '04A1B2C3', 'user_id' => $user->id]);

        $this->callTerminal('/api/v1/bookinginfo/04A1B2C3')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'subject' => 'Max', 'text' => 'Nicht eingestempelt · heute 0:00 h']);

        // Earlier closed booking of 2 h today, plus one running since 08:00.
        Booking::factory()->create([
            'user_id' => $user->id,
            'start_at' => now()->subHours(3),
            'end_at' => now()->subHour(),
        ]);
        Booking::factory()->create(['user_id' => $user->id, 'start_at' => now()]);

        Carbon::setTestNow(now()->addMinutes(75));

        $this->callTerminal('/api/v1/bookinginfo/04A1B2C3')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'subject' => 'Max', 'text' => 'Anwesend seit 08:00 (1:15 h) · heute 3:15 h']);

        $this->assertDatabaseCount('bookings', 2);
        $this->assertSame(TerminalEventResult::Info, TerminalEvent::latest('id')->first()->result);
    }
}
