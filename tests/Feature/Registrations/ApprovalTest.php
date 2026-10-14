<?php

namespace Tests\Feature\Registrations;

use App\Enums\RegistrationStatus;
use App\Filament\Resources\CourseInstances\Pages\EditCourseInstance;
use App\Filament\Resources\CourseInstances\RelationManagers\ParticipantsRelationManager;
use App\Filament\Resources\Registrations\Pages\ListRegistrations;
use App\Filament\Resources\Registrations\Pages\ViewRegistration;
use App\Mail\ApprovalRequestMail;
use App\Mail\RegistrationAwaitingApprovalMail;
use App\Mail\RegistrationConfirmationMail;
use App\Models\Cart;
use App\Models\CompanySetting;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\Registration;
use App\Models\User;
use App\Services\Registrations\CourseReservationService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected CourseReservationService $reservations;

    protected CourseInstance $instance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();
        $this->reservations = app(CourseReservationService::class);

        $lead = User::factory()->create(['name' => 'Sylvia', 'email' => 'lead@example.com']);
        $course = Course::factory()->create(['responsible_user_id' => $lead->id, 'min_age' => null, 'max_age' => null]);
        $this->instance = CourseInstance::factory()->for($course)->withDates()->create(['capacity' => 2]);
    }

    protected function book(array $kidNames, string $email): Registration
    {
        $cart = Cart::factory()->create();
        $registration = $this->reservations->hold($this->instance, count($kidNames), $cart);
        $this->reservations->checkout($cart, ['email' => $email, 'name' => 'Familie'], [
            $registration->id => array_map(fn (string $name): array => ['name' => $name, 'birthdate' => now()->subYears(10)->toDateString()], $kidNames),
        ]);
        $token = basename(parse_url(Mail::sent(RegistrationConfirmationMail::class)->last()->confirmationUrl, PHP_URL_PATH));
        $this->reservations->confirm($token);

        return $registration->fresh();
    }

    protected function siblingsAwaitingApproval(): Registration
    {
        $this->book(['Max'], 'erste@example.com');

        return $this->book(['Paul', 'Lena'], 'geschwister@example.com');
    }

    public function test_approval_mail_contains_all_infos_and_both_buttons(): void
    {
        $registration = $this->siblingsAwaitingApproval();

        $mail = Mail::sent(ApprovalRequestMail::class)->sole();
        $html = $mail->render();

        $this->assertTrue($mail->hasTo('lead@example.com'));
        $this->assertTrue($mail->hasReplyTo('geschwister@example.com'));
        $this->assertStringContainsString('Paul', $html);
        $this->assertStringContainsString('Lena', $html);
        $this->assertStringContainsString('10 Jahre', $html);
        $this->assertStringContainsString('Freigeben', $html);
        $this->assertStringContainsString('Ablehnen', $html);
        $this->assertStringContainsString(e($mail->approveUrl), $html);
        $this->assertStringContainsString(e($mail->declineUrl), $html);
        $this->assertSame(RegistrationStatus::AwaitingApproval, $registration->status);

        $parentMail = Mail::sent(RegistrationAwaitingApprovalMail::class)->sole();
        $this->assertStringContainsString('prüfen', $parentMail->render());
    }

    public function test_approval_falls_back_to_company_contact_without_responsible_person(): void
    {
        CompanySetting::current()->update(['contact_email' => 'team@example.com']);
        $this->instance->course->update(['responsible_user_id' => null]);

        $this->siblingsAwaitingApproval();

        Mail::assertSent(ApprovalRequestMail::class, fn ($mail): bool => $mail->hasTo('team@example.com'));
    }

    public function test_responsible_person_of_instance_overrides_course(): void
    {
        $instanceLead = User::factory()->create(['email' => 'instanz@example.com']);
        $this->instance->update(['responsible_user_id' => $instanceLead->id]);

        $this->siblingsAwaitingApproval();

        Mail::assertSent(ApprovalRequestMail::class, fn ($mail): bool => $mail->hasTo('instanz@example.com'));
    }

    public function test_signed_link_shows_decision_page_and_button_approves(): void
    {
        $registration = $this->siblingsAwaitingApproval();
        $approveUrl = Mail::sent(ApprovalRequestMail::class)->sole()->approveUrl;

        $page = $this->get($approveUrl)->assertOk()->assertSee('Jetzt freigeben')->assertSee('Paul');
        $this->assertSame(RegistrationStatus::AwaitingApproval, $registration->fresh()->status, 'Opening the link alone must not decide.');

        preg_match('/<form method="POST" action="([^"]+)"/', $page->getContent(), $matches);
        $this->post(html_entity_decode($matches[1]))->assertRedirect();

        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
        $this->get($approveUrl)->assertSee('Erledigt');
        $this->get($approveUrl)->assertSee('nichts mehr zu entscheiden');
    }

    public function test_signed_link_can_decline(): void
    {
        $registration = $this->siblingsAwaitingApproval();
        $declineUrl = Mail::sent(ApprovalRequestMail::class)->sole()->declineUrl;

        $page = $this->get($declineUrl)->assertSee('Jetzt ablehnen');
        preg_match('/<form method="POST" action="([^"]+)"/', $page->getContent(), $matches);
        $this->post(html_entity_decode($matches[1]));

        $this->assertTrue($registration->fresh()->isOnWaitlist());
    }

    public function test_unsigned_or_tampered_links_are_rejected(): void
    {
        $registration = $this->siblingsAwaitingApproval();

        $this->get("/freigabe/{$registration->id}?entscheidung=freigeben")->assertForbidden();
        $this->post("/freigabe/{$registration->id}?entscheidung=freigeben")->assertForbidden();

        $approveUrl = Mail::sent(ApprovalRequestMail::class)->sole()->approveUrl;
        $this->get(str_replace('freigeben', 'ablehnen', $approveUrl))->assertForbidden();
    }

    public function test_staff_can_decide_in_the_cockpit(): void
    {
        $user = User::factory()->create();
        foreach (['ViewAny', 'View', 'Update'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:Registration"));
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:CourseInstance"));
        }
        $this->actingAs($user);

        $registration = $this->siblingsAwaitingApproval();

        Livewire::test(ListRegistrations::class)
            ->set('activeTab', 'approval')
            ->assertCanSeeTableRecords([$registration]);

        Livewire::test(ViewRegistration::class, ['record' => $registration->id])
            ->assertActionVisible('approveRegistration')
            ->callAction('declineRegistration');

        $this->assertTrue($registration->fresh()->isOnWaitlist());
    }

    public function test_staff_can_approve_from_the_participant_list(): void
    {
        $user = User::factory()->create();
        foreach (['ViewAny', 'View', 'Update'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:Registration"));
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:CourseInstance"));
        }
        $this->actingAs($user);

        $registration = $this->siblingsAwaitingApproval();
        $enrollment = $registration->enrollments->first();

        Livewire::test(ParticipantsRelationManager::class, ['ownerRecord' => $this->instance, 'pageClass' => EditCourseInstance::class])
            ->callAction(TestAction::make('approveRegistration')->table($enrollment))
            ->assertNotified('Freigegeben');

        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
    }

    public function test_course_page_explains_sibling_check_and_waitlist_deadline(): void
    {
        $this->book(['Max'], 'erste@example.com');

        $this->get($this->instance->course->url())
            ->assertSee('Noch 1 Platz frei. Meldest du mehr Geschwister an, prüfen wir, ob alle teilnehmen können.');

        $this->book(['Lena'], 'zweite@example.com');
        $this->instance->update(['waitlist_offer_hours' => 12]);

        $this->get($this->instance->course->url())
            ->assertSee('Auf die Warteliste')
            ->assertSee('12 Stunden Zeit');
    }
}
