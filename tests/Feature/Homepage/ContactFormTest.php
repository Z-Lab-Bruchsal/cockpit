<?php

namespace Tests\Feature\Homepage;

use App\Enums\ContactFormTopic;
use App\Mail\ContactFormMail;
use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();
        CompanySetting::current()->update(['contact_email' => 'team@z-lab.example']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validInput(array $overrides = []): array
    {
        return [
            'name' => 'Maria Muster',
            'email' => 'maria@example.com',
            'phone' => '07251 123456',
            'topic' => ContactFormTopic::Courses->value,
            'message' => "Hallo,\nist im Lasercutter-Kurs noch ein Platz frei?",
            'privacy' => '1',
            ...$overrides,
        ];
    }

    public function test_form_page_renders(): void
    {
        $this->get('/kontaktformular')
            ->assertOk()
            ->assertSee('Nachricht senden');
    }

    public function test_topic_can_be_preselected_via_link(): void
    {
        $this->get('/kontaktformular?anliegen=email-verloren')
            ->assertSee('<option value="email-verloren" selected', false)
            ->assertSee('Bisherige E-Mail-Adresse');
    }

    public function test_message_is_sent_to_the_team_with_reply_to_sender(): void
    {
        $this->from('/kontaktformular')
            ->post('/kontaktformular', $this->validInput())
            ->assertRedirect('/kontaktformular#kontaktformular')
            ->assertSessionHas('contact_form_sent');

        Mail::assertSent(ContactFormMail::class, fn (ContactFormMail $mail): bool => $mail->hasTo('team@z-lab.example')
            && $mail->hasReplyTo('maria@example.com', 'Maria Muster')
            && $mail->topic === ContactFormTopic::Courses
            && str_contains($mail->render(), 'Lasercutter-Kurs'));

        $this->get('/kontaktformular')->assertSee('Danke für deine Nachricht');
    }

    public function test_lost_email_request_contains_the_old_address(): void
    {
        $this->post('/kontaktformular', $this->validInput([
            'topic' => ContactFormTopic::LostEmailAccess->value,
            'old_email' => 'alt@example.com',
            'email' => 'neu@example.com',
        ]));

        Mail::assertSent(ContactFormMail::class, fn (ContactFormMail $mail): bool => $mail->oldEmail === 'alt@example.com'
            && str_contains($mail->render(), 'alt@example.com')
            && $mail->envelope()->subject === 'Kontaktformular: Kein Zugriff mehr auf meine E-Mail-Adresse');
    }

    public function test_input_is_validated(): void
    {
        $this->post('/kontaktformular', $this->validInput(['email' => 'kaputt', 'message' => '', 'privacy' => null, 'topic' => 'unbekannt']))
            ->assertSessionHasErrorsIn('contactForm', ['email', 'message', 'privacy', 'topic']);

        Mail::assertNothingSent();
    }

    public function test_honeypot_blocks_bots(): void
    {
        $this->post('/kontaktformular', $this->validInput(['website' => 'http://spam.example']))
            ->assertSessionHasErrorsIn('contactForm', ['website']);

        Mail::assertNothingSent();
    }

    public function test_form_is_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post('/kontaktformular', $this->validInput())->assertRedirect();
        }

        $this->post('/kontaktformular', $this->validInput())->assertTooManyRequests();
        Mail::assertSentCount(5);
    }

    public function test_logged_in_parent_gets_prefilled_form(): void
    {
        $this->actingAs(Contact::factory()->create(['name' => 'Paula Eltern', 'email' => 'paula@example.com']), 'contact');

        $this->get('/kontaktformular')
            ->assertSee('value="Paula Eltern"', false)
            ->assertSee('value="paula@example.com"', false);
    }

    public function test_form_can_be_placed_in_a_section(): void
    {
        Section::factory()->create([
            'slug' => 'kontakt',
            'content' => [['type' => 'contact_form', 'data' => ['intro' => '<p>Wir freuen uns auf deine Nachricht</p>', 'topic' => 'schulen']]],
        ]);

        $this->get('/kontakt')
            ->assertOk()
            ->assertSee('Wir freuen uns auf deine Nachricht')
            ->assertSee('<option value="schulen" selected', false);
    }

    public function test_account_and_login_link_to_the_form_for_lost_email_access(): void
    {
        $url = route('homepage.contact-form', ['anliegen' => 'email-verloren']);

        $this->get('/anmelden')->assertSee($url, false);

        $this->actingAs(Contact::factory()->create(), 'contact');
        $this->get('/konto')->assertSee($url, false);
    }
}
