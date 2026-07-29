<?php

namespace Tests\Feature\TimeClock;

use App\Enums\TimeEntryType;
use App\Mail\WorkTimeComplianceMail;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendBreakComplianceWarningsTest extends TestCase
{
    use RefreshDatabase;

    private function punch(User $user, TimeEntryType $type, string $time): void
    {
        TimeEntry::create([
            'user_id' => $user->id,
            'type' => $type,
            'happened_at' => $time,
            'recorded_by_user_id' => $user->id,
        ]);
    }

    public function test_sends_a_warning_email_for_a_user_with_insufficient_break(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $this->punch($user, TimeEntryType::Come, today()->setTime(8, 0));
        $this->punch($user, TimeEntryType::Go, today()->setTime(14, 1));

        $this->artisan('app:send-break-compliance-warnings')->assertSuccessful();

        Mail::assertSent(WorkTimeComplianceMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_does_not_send_an_email_for_a_compliant_user(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $this->punch($user, TimeEntryType::Come, today()->setTime(8, 0));
        $this->punch($user, TimeEntryType::Go, today()->setTime(12, 0));

        $this->artisan('app:send-break-compliance-warnings')->assertSuccessful();

        Mail::assertNotSent(WorkTimeComplianceMail::class);
    }
}
