<?php

namespace Tests\Feature\Schools;

use App\Enums\ContactType;
use App\Enums\RegistrationStatus;
use App\Livewire\Schools\SchoolAccount;
use App\Mail\ContactMagicLinkMail;
use App\Mail\SchoolLoginLinkMail;
use App\Models\Contact;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class SchoolAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();
        RateLimiter::clear('magic-link');
    }

    /**
     * @return array<string, string>
     */
    protected function registrationData(array $overrides = []): array
    {
        return [
            'name' => 'Grundschule am Wald',
            'street' => 'Waldweg 1',
            'zip' => '76646',
            'city' => 'Bruchsal',
            'email' => 'sekretariat@gs-wald.de',
            'accepts_privacy' => '1',
            ...$overrides,
        ];
    }

    protected function tokenFrom(string $url): string
    {
        return basename(parse_url($url, PHP_URL_PATH));
    }

    public function test_landing_and_registration_pages_render(): void
    {
        $this->get('/schulen')->assertOk()->assertSee('Schule registrieren')->assertSee('Funktionsadresse');
        $this->get('/schulen/registrieren')->assertOk()->assertSee('Name der Schule');
        $this->get('/')->assertOk()->assertSee(route('homepage.schools.index'), false);
    }

    public function test_school_registers_and_logs_in_after_confirming_the_function_address(): void
    {
        $this->post('/schulen/registrieren', $this->registrationData())
            ->assertRedirect('/schulen/registrieren')
            ->assertSessionHas('school_registration_sent', 'sekretariat@gs-wald.de');

        $school = Contact::query()->where('email', 'sekretariat@gs-wald.de')->firstOrFail();
        $this->assertSame(ContactType::School, $school->type);
        $this->assertSame('Waldweg 1, 76646 Bruchsal', $school->addressLine());
        $this->assertNull($school->email_verified_at);

        $mail = Mail::sent(SchoolLoginLinkMail::class)->last();
        $this->assertSame(SchoolLoginLinkMail::REGISTRATION, $mail->kind);

        $this->post('/login/'.$this->tokenFrom($mail->url))->assertRedirect('/schulen/konto');

        $this->assertAuthenticatedAs($school, 'contact');
        $this->assertNotNull($school->fresh()->email_verified_at);
        $this->get('/schulen/konto')->assertOk()->assertSee('Grundschule am Wald')->assertSee('Lehrkräfte');
    }

    public function test_school_registration_requires_the_address(): void
    {
        $this->post('/schulen/registrieren', $this->registrationData(['street' => '', 'zip' => '', 'city' => '', 'accepts_privacy' => null]))
            ->assertSessionHasErrors(['street', 'zip', 'city', 'accepts_privacy']);

        $this->assertDatabaseCount(Contact::class, 0);
    }

    public function test_registering_a_known_address_only_sends_a_login_link(): void
    {
        $parent = Contact::factory()->create(['email' => 'sekretariat@gs-wald.de']);

        $this->post('/schulen/registrieren', $this->registrationData())
            ->assertSessionHas('school_registration_sent');

        $this->assertSame(ContactType::Parent, $parent->fresh()->type);
        $this->assertDatabaseCount(Contact::class, 1);
        Mail::assertSent(ContactMagicLinkMail::class);
        Mail::assertNotSent(SchoolLoginLinkMail::class);
    }

    public function test_school_invites_a_teacher_who_logs_in_to_the_school_area(): void
    {
        $school = Contact::factory()->school()->create();

        Livewire::actingAs($school, 'contact')
            ->test(SchoolAccount::class)
            ->set('teacherName', 'Frau Berg')
            ->set('teacherEmail', 'berg@gs-wald.de')
            ->call('inviteTeacher')
            ->assertHasNoErrors();

        $teacher = Contact::query()->where('email', 'berg@gs-wald.de')->firstOrFail();
        $this->assertSame(ContactType::Teacher, $teacher->type);
        $this->assertTrue($teacher->school->is($school));

        $mail = Mail::sent(SchoolLoginLinkMail::class)->last();
        $this->assertSame(SchoolLoginLinkMail::INVITATION, $mail->kind);
        $this->assertTrue($mail->hasTo('berg@gs-wald.de'));

        auth('contact')->logout();
        $this->post('/login/'.$this->tokenFrom($mail->url))->assertRedirect('/schulen/konto');
        $this->assertAuthenticatedAs($teacher, 'contact');
        $this->get('/konto')->assertRedirect('/schulen/konto');
    }

    public function test_invitation_is_refused_for_an_address_of_another_contact(): void
    {
        $school = Contact::factory()->school()->create();
        Contact::factory()->create(['email' => 'papa@example.com']);

        Livewire::actingAs($school, 'contact')
            ->test(SchoolAccount::class)
            ->set('teacherName', 'Herr Papa')
            ->set('teacherEmail', 'papa@example.com')
            ->call('inviteTeacher')
            ->assertHasErrors('teacherEmail');

        Mail::assertNotSent(SchoolLoginLinkMail::class);
    }

    public function test_only_the_school_account_can_invite_and_remove_teachers(): void
    {
        $teacher = Contact::factory()->teacher()->create();
        $colleague = Contact::factory()->teacher($teacher->school)->create();

        Livewire::actingAs($teacher, 'contact')
            ->test(SchoolAccount::class)
            ->assertDontSee('Einladen')
            ->call('removeTeacher', $colleague->id)
            ->assertForbidden();

        Livewire::actingAs($teacher->school, 'contact')
            ->test(SchoolAccount::class)
            ->assertSee($colleague->name)
            ->call('removeTeacher', $colleague->id);

        $this->assertModelMissing($colleague);
    }

    public function test_school_sees_all_bookings_and_a_teacher_only_their_own(): void
    {
        $school = Contact::factory()->school()->create();
        $berg = Contact::factory()->teacher($school)->create();
        $stein = Contact::factory()->teacher($school)->create();
        $instance = CourseInstance::factory()->for(Course::factory()->forSchoolClasses()->create(['name' => 'Roboter bauen']))->withDates()->create();
        $other = CourseInstance::factory()->for(Course::factory()->forSchoolClasses()->create(['name' => 'Löten lernen']))->withDates()->create();

        Registration::factory()->for($instance, 'courseInstance')->create(['contact_id' => $berg->id, 'school_id' => $school->id, 'status' => RegistrationStatus::Confirmed, 'school_class_data' => ['class_name' => '4a', 'students' => 22, 'teacher_name' => 'Berg']]);
        Registration::factory()->for($other, 'courseInstance')->create(['contact_id' => $stein->id, 'school_id' => $school->id, 'status' => RegistrationStatus::Requested, 'school_class_data' => ['class_name' => '3b', 'students' => 20, 'teacher_name' => 'Stein']]);

        Livewire::actingAs($school, 'contact')->test(SchoolAccount::class)
            ->assertSee('Roboter bauen')->assertSee('Löten lernen');

        Livewire::actingAs($berg, 'contact')->test(SchoolAccount::class)
            ->assertSee('Roboter bauen')->assertDontSee('Löten lernen');
    }

    public function test_parents_and_guests_cannot_open_the_school_area(): void
    {
        $this->get('/schulen/konto')->assertRedirect(route('homepage.login', ['weiter' => '/schulen/konto']));

        $this->actingAs(Contact::factory()->create(), 'contact')
            ->get('/schulen/konto')
            ->assertRedirect('/schulen')
            ->assertSessionHas('school_notice');
    }
}
