<?php

namespace Tests\Feature\Contacts;

use App\Enums\ContactType;
use App\Mail\ContactMagicLinkMail;
use App\Models\Contact;
use App\Models\ContactLoginToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class MagicLinkTest extends TestCase
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
     * Requests a link and returns the plain token from the sent mail.
     */
    protected function requestLink(string $email, ?string $name = null, ?string $redirectTo = null): string
    {
        $this->post('/anmelden', array_filter(['email' => $email, 'name' => $name, 'redirect_to' => $redirectTo]))
            ->assertRedirect('/anmelden')
            ->assertSessionHas('magic_link_sent', trim($email));

        $mail = Mail::sent(ContactMagicLinkMail::class)->last();

        return basename(parse_url($mail->url, PHP_URL_PATH));
    }

    public function test_login_page_renders(): void
    {
        $this->get('/anmelden')->assertOk()->assertSee('Anmeldelink schicken');
    }

    public function test_known_contact_receives_login_link_and_can_log_in(): void
    {
        $contact = Contact::factory()->create(['email' => 'mama@example.com']);

        $token = $this->requestLink('Mama@Example.com ');

        Mail::assertSent(ContactMagicLinkMail::class, fn (ContactMagicLinkMail $mail): bool => $mail->hasTo('mama@example.com')
            && ! $mail->isNewContact
            && $mail->hasFrom(config('mail.from.address'), 'Z-Lab Bruchsal'));
        $this->assertDatabaseHas(ContactLoginToken::class, ['contact_id' => $contact->id, 'token_hash' => ContactLoginToken::hash($token)]);
        $this->assertDatabaseMissing(ContactLoginToken::class, ['token_hash' => $token]);

        $this->post("/login/{$token}")->assertRedirect('/konto');

        $this->assertAuthenticatedAs($contact, 'contact');
        $this->assertNotNull($contact->fresh()->last_login_at);
    }

    public function test_opening_the_link_does_not_log_in_yet(): void
    {
        Contact::factory()->create(['email' => 'mama@example.com']);
        $token = $this->requestLink('mama@example.com');

        $this->get("/login/{$token}")->assertOk()->assertSee('Jetzt anmelden');

        $this->assertGuest('contact');
        $this->assertNull(ContactLoginToken::query()->first()->used_at);
    }

    public function test_unknown_email_is_registered_only_when_the_link_is_used(): void
    {
        $token = $this->requestLink('neu@example.com', 'Nina Neu');

        Mail::assertSent(ContactMagicLinkMail::class, fn (ContactMagicLinkMail $mail): bool => $mail->isNewContact);
        $this->assertSame(0, Contact::query()->count());

        $this->post("/login/{$token}")->assertRedirect('/konto');

        $contact = Contact::query()->sole();
        $this->assertSame('Nina Neu', $contact->name);
        $this->assertSame(ContactType::Parent, $contact->type);
        $this->assertTrue($contact->hasVerifiedEmail());
        $this->assertFalse($contact->wantsInterestMails());
        $this->assertAuthenticatedAs($contact, 'contact');
    }

    public function test_link_can_only_be_used_once(): void
    {
        Contact::factory()->create(['email' => 'mama@example.com']);
        $token = $this->requestLink('mama@example.com');

        $this->post("/login/{$token}")->assertRedirect('/konto');
        $this->post('/logout');

        $this->post("/login/{$token}")->assertRedirect("/login/{$token}");
        $this->assertGuest('contact');
        $this->get("/login/{$token}")->assertSee('Link nicht mehr gültig');
    }

    public function test_expired_link_is_rejected(): void
    {
        Contact::factory()->create(['email' => 'mama@example.com']);
        $token = $this->requestLink('mama@example.com');

        $this->travel(31)->minutes();

        $this->post("/login/{$token}");
        $this->assertGuest('contact');
    }

    public function test_redirect_after_login_only_allows_local_paths(): void
    {
        Contact::factory()->create(['email' => 'mama@example.com']);

        $token = $this->requestLink('mama@example.com', redirectTo: '/kurse/lasercutter');
        $this->post("/login/{$token}")->assertRedirect('/kurse/lasercutter');
        $this->post('/logout');

        $token = $this->requestLink('mama@example.com', redirectTo: '//evil.example.com');
        $this->post("/login/{$token}")->assertRedirect('/konto');
    }

    public function test_honeypot_blocks_bots(): void
    {
        $this->post('/anmelden', ['email' => 'bot@example.com', 'website' => 'spam'])->assertSessionHasErrors('website');

        Mail::assertNothingSent();
    }

    public function test_requests_per_address_are_rate_limited(): void
    {
        foreach (range(1, 3) as $attempt) {
            $this->post('/anmelden', ['email' => 'flood@example.com'])->assertRedirect();
        }

        $this->post('/anmelden', ['email' => 'flood@example.com'])->assertTooManyRequests();
        Mail::assertSentCount(3);
    }

    public function test_account_requires_login(): void
    {
        $this->get('/konto')->assertRedirect('/anmelden');
    }

    public function test_logged_in_contact_is_sent_from_login_page_to_account(): void
    {
        $this->actingAs(Contact::factory()->create(), 'contact');

        $this->get('/anmelden')->assertRedirect('/konto');
        $this->get('/')->assertSee('Mein Konto');
    }

    public function test_contact_login_does_not_grant_cockpit_access(): void
    {
        $this->actingAs(Contact::factory()->create(), 'contact');

        $this->get('/cockpit')->assertRedirect('/cockpit/login');
    }
}
