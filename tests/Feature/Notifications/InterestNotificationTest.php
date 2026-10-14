<?php

namespace Tests\Feature\Notifications;

use App\Enums\CourseInstanceStatus;
use App\Filament\Resources\CourseInstances\Pages\CreateCourseInstance;
use App\Filament\Resources\CourseInstances\Pages\EditCourseInstance;
use App\Mail\CourseDigestMail;
use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\CourseInstanceNotification;
use App\Models\Interest;
use App\Models\Kid;
use App\Models\User;
use App\Services\Notifications\InterestNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InterestNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Interest $robotics;

    protected Interest $sewing;

    protected CourseInstance $instance;

    protected InterestNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();

        $this->robotics = Interest::factory()->create(['name' => 'Robotik']);
        $this->sewing = Interest::factory()->create(['name' => 'Nähen']);

        $course = Course::factory()->create(['name' => 'Roboter bauen', 'min_age' => 8, 'max_age' => 12]);
        $course->interests()->attach($this->robotics);
        $this->instance = CourseInstance::factory()->for($course)->withDates(2, now()->addMonth()->setTime(16, 0))->create();

        $this->service = app(InterestNotificationService::class);
    }

    /**
     * Parent with consent and one child of the given age and interests.
     */
    protected function parentWithKid(int $age, array $interests, array $contactState = []): Contact
    {
        $contact = Contact::factory()->withInterestMailsConsent()->create($contactState);
        $kid = Kid::factory()->create(['name' => 'Kind '.$contact->id, 'birthdate' => now()->addMonth()->subYears($age)->subMonths(2)->toDateString()]);
        $kid->interests()->attach(collect($interests)->pluck('id'));
        $contact->kids()->attach($kid);

        return $contact;
    }

    protected function defaultRecipients()
    {
        $filters = $this->service->defaultFilters($this->instance);

        return $this->service->recipients($this->instance, $filters['interest_ids'], $filters['min_age'], $filters['max_age']);
    }

    public function test_only_parents_with_matching_child_and_consent_are_recipients(): void
    {
        $match = $this->parentWithKid(10, [$this->robotics]);
        $wrongInterest = $this->parentWithKid(10, [$this->sewing]);
        $tooYoung = $this->parentWithKid(6, [$this->robotics]);
        $tooOld = $this->parentWithKid(13, [$this->robotics]);
        $noConsent = $this->parentWithKid(10, [$this->robotics], ['interest_mails_consent_at' => null]);
        $unverified = $this->parentWithKid(10, [$this->robotics], ['email_verified_at' => null]);

        $recipients = $this->defaultRecipients();

        $this->assertEquals([$match->id], $recipients->pluck('id')->all());
    }

    public function test_age_is_checked_at_the_first_date(): void
    {
        // 12 today, but 13 at the first course date
        $contact = Contact::factory()->withInterestMailsConsent()->create();
        $kid = Kid::factory()->create(['birthdate' => now()->addWeeks(2)->subYears(13)->toDateString()]);
        $kid->interests()->attach($this->robotics);
        $contact->kids()->attach($kid);

        $this->assertCount(0, $this->defaultRecipients());
    }

    public function test_children_without_birthdate_match_regardless_of_age(): void
    {
        $contact = Contact::factory()->withInterestMailsConsent()->create();
        $kid = Kid::factory()->create(['birthdate' => null]);
        $kid->interests()->attach($this->robotics);
        $contact->kids()->attach($kid);

        $this->assertEquals([$contact->id], $this->defaultRecipients()->pluck('id')->all());
        $this->assertCount(0, $this->service->recipients($this->instance, [$this->sewing->id], 8, 12), 'The interest filter still applies.');
    }

    public function test_without_interest_filter_every_child_in_the_age_range_matches(): void
    {
        $this->parentWithKid(10, [$this->sewing]);
        $this->parentWithKid(10, []);

        $this->assertCount(2, $this->service->recipients($this->instance, [], 8, 12));
    }

    public function test_courses_are_queued_and_sent_as_digest_after_quiet_period(): void
    {
        CompanySetting::current()->update(['digest_hours' => 4]);
        $contact = Contact::factory()->withInterestMailsConsent()->create(['name' => 'Maria']);
        foreach (['Paul' => 9, 'Lena' => 11, 'Max' => 15] as $name => $age) {
            $kid = Kid::factory()->create(['name' => $name, 'birthdate' => now()->addMonth()->subYears($age)->subMonths(2)->toDateString()]);
            $kid->interests()->attach($this->robotics);
            $contact->kids()->attach($kid);
        }

        $this->assertSame(1, $this->service->notify($this->instance, [$this->robotics->id], 8, 12));
        $this->assertSame(0, $this->service->notify($this->instance, [$this->robotics->id], 8, 12), 'Nobody is queued twice.');
        Mail::assertNothingQueued();

        // A second course arrives three hours later: the quiet period starts again.
        $this->travel(3)->hours();
        $second = CourseInstance::factory()->for(Course::factory()->create(['name' => 'Löten', 'min_age' => null, 'max_age' => null]))->withDates(1, now()->addMonths(2))->create();
        $this->service->notify($second, [], null, null);

        $this->travel(3)->hours();
        $this->assertSame(0, $this->service->sendDueDigests());

        $this->travel(2)->hours();
        $this->assertSame(1, $this->service->sendDueDigests());

        Mail::assertQueued(CourseDigestMail::class, 1);
        Mail::assertQueued(CourseDigestMail::class, function (CourseDigestMail $mail) use ($contact): bool {
            $items = $mail->items();

            return $mail->hasTo($contact->email)
                && $items->count() === 2
                && $items->first()['kids'] === ['Lena (11)', 'Paul (9)']
                && count($items->last()['kids']) === 3;
        });
        $this->assertSame(0, CourseInstanceNotification::query()->pending()->count());
        $this->assertSame(0, $this->service->sendDueDigests());
    }

    public function test_contact_can_choose_own_digest_time(): void
    {
        CompanySetting::current()->update(['digest_hours' => 4]);
        $contact = $this->parentWithKid(10, [$this->robotics], ['digest_hours' => 24]);

        $this->service->notify($this->instance, [$this->robotics->id], 8, 12);

        $this->travel(5)->hours();
        $this->assertSame(0, $this->service->sendDueDigests());

        $this->travel(20)->hours();
        $this->assertSame(1, $this->service->sendDueDigests());
        Mail::assertQueued(CourseDigestMail::class, fn ($mail): bool => $mail->hasTo($contact->email));
    }

    public function test_weekly_maximum_postpones_further_digests(): void
    {
        CompanySetting::current()->update(['digest_hours' => 1]);
        $this->parentWithKid(10, [], ['max_course_mails_per_week' => 1]);

        $this->service->notify($this->instance, [], null, null);
        $this->travel(2)->hours();
        $this->assertSame(1, $this->service->sendDueDigests());

        $later = CourseInstance::factory()->withDates(1, now()->addMonths(2))->create();
        $this->service->notify($later, [], null, null);
        $this->travel(2)->hours();
        $this->assertSame(0, $this->service->sendDueDigests(), 'Only one mail per week.');

        $this->travel(7)->days();
        $this->assertSame(1, $this->service->sendDueDigests());
        Mail::assertQueued(CourseDigestMail::class, 2);
    }

    public function test_withdrawn_consent_and_cancelled_courses_are_not_mailed(): void
    {
        CompanySetting::current()->update(['digest_hours' => 1]);
        $withdrawn = $this->parentWithKid(10, [$this->robotics]);
        $this->parentWithKid(10, [$this->robotics]);

        $this->service->notify($this->instance, [$this->robotics->id], 8, 12);
        $withdrawn->update(['interest_mails_consent_at' => null]);
        $this->travel(2)->hours();

        $this->assertSame(1, $this->service->sendDueDigests());
        Mail::assertNotQueued(CourseDigestMail::class, fn ($mail): bool => $mail->hasTo($withdrawn->email));

        $cancelled = CourseInstance::factory()->withDates(1, now()->addMonth())->create();
        $this->service->notify($cancelled, [], null, null);
        $cancelled->update(['status' => CourseInstanceStatus::Cancelled]);
        $this->travel(2)->hours();

        $this->assertSame(0, $this->service->sendDueDigests());
        Mail::assertQueued(CourseDigestMail::class, 1);
        $this->assertSame(0, CourseInstanceNotification::query()->pending()->count());
    }

    public function test_digest_command_runs(): void
    {
        CompanySetting::current()->update(['digest_hours' => 1]);
        $this->parentWithKid(10, [$this->robotics]);
        $this->service->notify($this->instance, [$this->robotics->id], 8, 12);
        $this->travel(2)->hours();

        $this->artisan('app:send-course-digests')->expectsOutputToContain('1 Sammelmails verschickt')->assertSuccessful();
    }

    public function test_digest_mail_contains_courses_kids_and_unsubscribe_options(): void
    {
        $contact = $this->parentWithKid(10, [$this->robotics]);
        $this->service->notify($this->instance, [$this->robotics->id], 8, 12);
        $mail = new CourseDigestMail($contact, CourseInstanceNotification::query()->pluck('id')->all());

        $html = $mail->render();

        $this->assertStringContainsString('Roboter bauen', $html);
        $this->assertStringContainsString('Passt zu', $html);
        $this->assertStringContainsString(e($this->instance->course->url().'#durchfuehrung-'.$this->instance->id), $html);
        $this->assertStringContainsString(route('homepage.unsubscribe', $contact->unsubscribe_token), $html);
        $this->assertSame('Neuer Kurs: Roboter bauen', $mail->envelope()->subject);
        $this->assertSame('<'.route('homepage.unsubscribe', $contact->unsubscribe_token).'>', $mail->headers()->text['List-Unsubscribe']);
        $this->assertSame('List-Unsubscribe=One-Click', $mail->headers()->text['List-Unsubscribe-Post']);
    }

    public function test_unsubscribe_page_needs_a_click(): void
    {
        $contact = $this->parentWithKid(10, [$this->robotics]);
        $url = route('homepage.unsubscribe', $contact->unsubscribe_token);

        $this->get($url)->assertOk()->assertSee('Ja, abbestellen');
        $this->assertTrue($contact->fresh()->wantsInterestMails());

        $this->post(route('homepage.unsubscribe.confirm', $contact->unsubscribe_token))->assertRedirect($url);
        $this->assertFalse($contact->fresh()->wantsInterestMails());
        $this->get($url)->assertSee('Abgemeldet');

        $this->get(route('homepage.unsubscribe', 'falsch'))->assertNotFound();
    }

    public function test_one_click_unsubscribe_works_without_csrf_token(): void
    {
        $contact = $this->parentWithKid(10, [$this->robotics]);

        $this->post(route('homepage.unsubscribe.confirm', $contact->unsubscribe_token), ['List-Unsubscribe' => 'One-Click'])
            ->assertNoContent();

        $this->assertFalse($contact->fresh()->wantsInterestMails());
    }

    protected function actingAsStaff(): void
    {
        $user = User::factory()->create();

        foreach (['ViewAny', 'View', 'Create', 'Update'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:CourseInstance"));
        }

        $this->actingAs($user);
    }

    public function test_cockpit_action_shows_recipient_count_and_sends(): void
    {
        $this->actingAsStaff();
        $this->parentWithKid(10, [$this->robotics]);
        $this->parentWithKid(9, [$this->robotics]);
        $this->parentWithKid(10, [$this->sewing]);

        Livewire::test(EditCourseInstance::class, ['record' => $this->instance->id])
            ->mountAction('notifyInterested')
            ->assertSchemaStateSet(['interest_ids' => [$this->robotics->id], 'min_age' => 8, 'max_age' => 12])
            ->assertMountedActionModalSee('2 Familien werden benachrichtigt.')
            ->callMountedAction()
            ->assertNotified('Für 2 Familien vorgemerkt');

        $this->assertSame(2, CourseInstanceNotification::query()->pending()->count());

        Livewire::test(EditCourseInstance::class, ['record' => $this->instance->id])
            ->mountAction('notifyInterested')
            ->assertMountedActionModalSee('Alle 2 passenden Familien sind schon vorgemerkt oder benachrichtigt.');
    }

    public function test_action_is_hidden_for_drafts(): void
    {
        $this->actingAsStaff();
        $this->instance->update(['status' => CourseInstanceStatus::Draft]);

        Livewire::test(EditCourseInstance::class, ['record' => $this->instance->id])
            ->assertActionHidden('notifyInterested');
    }

    public function test_new_instance_can_notify_right_away(): void
    {
        $this->actingAsStaff();
        $this->parentWithKid(10, [$this->robotics]);

        Livewire::test(CreateCourseInstance::class)
            ->fillForm([
                'course_id' => $this->instance->course_id,
                'status' => CourseInstanceStatus::Published->value,
                'capacity' => 10,
                'notify_interested' => true,
                'dates' => [['starts_at' => now()->addMonths(2)->setTime(16, 0)->toDateTimeString(), 'ends_at' => now()->addMonths(2)->setTime(17, 30)->toDateTimeString()]],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Neuer Kurs für 1 Familie vorgemerkt');

        $this->assertSame(1, CourseInstanceNotification::query()->pending()->count());
    }

    public function test_new_instance_without_toggle_sends_nothing(): void
    {
        $this->actingAsStaff();
        $this->parentWithKid(10, [$this->robotics]);

        Livewire::test(CreateCourseInstance::class)
            ->fillForm([
                'course_id' => $this->instance->course_id,
                'status' => CourseInstanceStatus::Published->value,
                'capacity' => 10,
                'dates' => [['starts_at' => now()->addMonths(2)->setTime(16, 0)->toDateTimeString(), 'ends_at' => now()->addMonths(2)->setTime(17, 30)->toDateTimeString()]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(0, CourseInstanceNotification::query()->count());
    }
}
