<?php

namespace Tests\Feature\Courses;

use App\Enums\EnrollmentStatus;
use App\Filament\Resources\CourseInstances\Pages\EditCourseInstance;
use App\Filament\Resources\CourseInstances\RelationManagers\MessagesRelationManager;
use App\Filament\Resources\CourseInstances\RelationManagers\ParticipantsRelationManager;
use App\Mail\ParticipantMessageMail;
use App\Models\CompanySetting;
use App\Models\CourseInstance;
use App\Models\CourseInstanceMessage;
use App\Models\Enrollment;
use App\Models\Registration;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ParticipantMessageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected CourseInstance $instance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();

        $this->user = User::factory()->create();

        foreach (['ViewAny', 'View', 'Update'] as $ability) {
            $this->user->givePermissionTo(Permission::findOrCreate("{$ability}:CourseInstance"));
        }

        $this->actingAs($this->user);

        $this->instance = CourseInstance::factory()->withDates()->create();
    }

    protected function enroll(string $email, EnrollmentStatus $status = EnrollmentStatus::Confirmed, int $kids = 1): Registration
    {
        $registration = Registration::factory()->create(['course_instance_id' => $this->instance->id, 'email' => $email, 'contact_name' => 'Maria']);
        Enrollment::factory()->count($kids)->create(['registration_id' => $registration->id, 'status' => $status]);

        return $registration;
    }

    protected function participants(): mixed
    {
        return Livewire::test(ParticipantsRelationManager::class, ['ownerRecord' => $this->instance, 'pageClass' => EditCourseInstance::class]);
    }

    public function test_confirmed_participants_get_the_message_once_per_family(): void
    {
        $maria = $this->enroll('maria@example.com', kids: 2);
        $this->enroll('MARIA@example.com');
        $this->enroll('tom@example.com');
        $this->enroll('wait@example.com', EnrollmentStatus::Waitlisted);
        $this->enroll('gone@example.com', EnrollmentStatus::Cancelled);

        $this->participants()
            ->callAction(TestAction::make('messageParticipants')->table(), [
                'statuses' => [EnrollmentStatus::Confirmed->value],
                'subject' => 'Raumänderung',
                'body' => 'Wir treffen uns im Raum 2.',
            ])
            ->assertHasNoFormErrors()
            ->assertNotified('An 2 Familien gesendet');

        Mail::assertQueuedCount(2);
        Mail::assertQueued(ParticipantMessageMail::class, fn (ParticipantMessageMail $mail): bool => $mail->hasTo('maria@example.com'));
        Mail::assertQueued(ParticipantMessageMail::class, fn (ParticipantMessageMail $mail): bool => $mail->hasTo('tom@example.com'));

        $message = CourseInstanceMessage::query()->sole();
        $this->assertSame($this->user->id, $message->user_id);
        $this->assertSame('Raumänderung', $message->subject);
        $this->assertSame($maria->id, $message->recipients[0]['registration_id']);
        $this->assertCount(2, $message->recipients);
    }

    public function test_waitlisted_participants_only_get_it_when_selected_and_cancelled_never(): void
    {
        $this->enroll('wait@example.com', EnrollmentStatus::Waitlisted);
        $this->enroll('gone@example.com', EnrollmentStatus::Cancelled);

        $this->participants()
            ->callAction(TestAction::make('messageParticipants')->table(), [
                'statuses' => [EnrollmentStatus::Cancelled->value],
                'subject' => 'Storniert',
                'body' => 'Nicht erlaubt.',
            ])
            ->assertHasFormErrors(['statuses.0']);

        $this->participants()
            ->callAction(TestAction::make('messageParticipants')->table(), [
                'statuses' => [EnrollmentStatus::Waitlisted->value],
                'subject' => 'Warteliste',
                'body' => 'Noch etwas Geduld.',
            ])
            ->assertNotified('An 1 Familie gesendet');

        Mail::assertQueuedCount(1);
        Mail::assertQueued(ParticipantMessageMail::class, fn (ParticipantMessageMail $mail): bool => $mail->hasTo('wait@example.com'));
    }

    public function test_nothing_is_sent_without_recipients(): void
    {
        Enrollment::factory()->create([
            'registration_id' => Registration::factory()->create(['course_instance_id' => $this->instance->id, 'email' => null])->id,
        ]);

        $this->participants()
            ->callAction(TestAction::make('messageParticipants')->table(), [
                'statuses' => [EnrollmentStatus::Confirmed->value],
                'subject' => 'Info',
                'body' => 'Text',
            ])
            ->assertNotified('Keine Empfänger mit E-Mail-Adresse');

        Mail::assertNothingQueued();
        $this->assertSame(0, CourseInstanceMessage::query()->count());
    }

    public function test_subject_and_body_are_required(): void
    {
        $this->participants()
            ->callAction(TestAction::make('messageParticipants')->table(), [
                'statuses' => [EnrollmentStatus::Confirmed->value],
                'subject' => '',
                'body' => '',
            ])
            ->assertHasFormErrors(['subject' => 'required', 'body' => 'required']);
    }

    public function test_bulk_action_only_mails_the_selected_participants(): void
    {
        $maria = $this->enroll('maria@example.com');
        $this->enroll('tom@example.com');

        $this->participants()
            ->selectTableRecords([$maria->enrollments->first()->id])
            ->callAction(TestAction::make('messageSelectedParticipants')->table()->bulk(), [
                'subject' => 'Nur für dich',
                'body' => 'Hallo',
            ])
            ->assertNotified('An 1 Familie gesendet');

        Mail::assertQueuedCount(1);
        Mail::assertQueued(ParticipantMessageMail::class, fn (ParticipantMessageMail $mail): bool => $mail->hasTo('maria@example.com'));
    }

    public function test_users_without_update_permission_cannot_send(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::findOrCreate('ViewAny:CourseInstance'), Permission::findOrCreate('View:CourseInstance'));
        $this->actingAs($viewer);

        $this->participants()
            ->assertActionHidden(TestAction::make('messageParticipants')->table());
    }

    public function test_history_lists_the_sent_message(): void
    {
        $message = CourseInstanceMessage::factory()->create(['course_instance_id' => $this->instance->id, 'subject' => 'Ausfall am Montag']);

        Livewire::test(MessagesRelationManager::class, ['ownerRecord' => $this->instance, 'pageClass' => EditCourseInstance::class])
            ->assertCanSeeTableRecords([$message])
            ->assertSee('Ausfall am Montag');
    }

    public function test_mail_renders_subject_body_and_homepage_sender_without_reply_to(): void
    {
        $registration = $this->enroll('maria@example.com');
        $message = CourseInstanceMessage::factory()->create([
            'course_instance_id' => $this->instance->id,
            'subject' => 'Raumänderung',
            'body' => "Bitte <b>Hausschuhe</b> mitbringen.\n\nBis bald!",
        ]);

        $mail = new ParticipantMessageMail($message, $registration);

        $mail->assertHasSubject('Raumänderung');
        $mail->assertFrom(config('mail.from.address'), CompanySetting::current()->homepageTitle());
        $this->assertSame([], $mail->envelope()->replyTo);
        $mail->assertSeeInHtml('Hallo Maria');
        $mail->assertSeeInHtml('&lt;b&gt;Hausschuhe&lt;/b&gt;', false);
        $mail->assertSeeInHtml('Bis bald!');
        $mail->assertSeeInHtml($this->instance->displayTitle());
    }
}
