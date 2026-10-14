<?php

namespace Tests\Feature\Contacts;

use App\Filament\Resources\Contacts\Pages\EditContact;
use App\Livewire\Homepage\Account;
use App\Mail\ContactEmailChangeMail;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmailChangeTest extends TestCase
{
    use RefreshDatabase;

    protected Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();

        $this->contact = Contact::factory()->create(['email' => 'alt@example.com']);
    }

    protected function tokenFrom(string $step): string
    {
        $mail = Mail::sent(ContactEmailChangeMail::class, fn (ContactEmailChangeMail $mail): bool => $mail->step === $step)->last();

        return basename(parse_url($mail->url, PHP_URL_PATH));
    }

    protected function requestChange(string $newEmail): void
    {
        $this->actingAs($this->contact, 'contact');

        Livewire::test(Account::class)
            ->set('newEmail', $newEmail)
            ->call('requestEmailChange')
            ->assertHasNoErrors();
    }

    public function test_email_is_changed_after_confirming_old_and_new_address(): void
    {
        $this->requestChange('Neu@Example.com');

        Mail::assertSent(ContactEmailChangeMail::class, fn (ContactEmailChangeMail $mail): bool => $mail->hasTo('alt@example.com')
            && $mail->step === ContactEmailChangeMail::STEP_CONFIRM_OLD
            && $mail->newEmail === 'neu@example.com');
        $this->assertSame('alt@example.com', $this->contact->fresh()->email);

        $oldToken = $this->tokenFrom(ContactEmailChangeMail::STEP_CONFIRM_OLD);
        $this->get("/email-aendern/{$oldToken}")->assertOk()->assertSee('neu@example.com');
        $this->post("/email-aendern/{$oldToken}")->assertRedirect("/email-aendern/{$oldToken}");

        Mail::assertSent(ContactEmailChangeMail::class, fn (ContactEmailChangeMail $mail): bool => $mail->hasTo('neu@example.com')
            && $mail->step === ContactEmailChangeMail::STEP_CONFIRM_NEW);
        $this->assertSame('alt@example.com', $this->contact->fresh()->email);

        $newToken = $this->tokenFrom(ContactEmailChangeMail::STEP_CONFIRM_NEW);
        $this->post("/email-aendern/{$newToken}")->assertRedirect();
        $this->get("/email-aendern/{$newToken}")->assertSee('E-Mail-Adresse geändert');

        $contact = $this->contact->fresh();
        $this->assertSame('neu@example.com', $contact->email);
        $this->assertTrue($contact->hasVerifiedEmail());

        Mail::assertSent(ContactEmailChangeMail::class, fn (ContactEmailChangeMail $mail): bool => $mail->hasTo('alt@example.com')
            && $mail->step === ContactEmailChangeMail::STEP_CHANGED);
    }

    public function test_opening_a_link_does_not_confirm_anything(): void
    {
        $this->requestChange('neu@example.com');
        $oldToken = $this->tokenFrom(ContactEmailChangeMail::STEP_CONFIRM_OLD);

        $this->get("/email-aendern/{$oldToken}")->assertOk();

        Mail::assertNotSent(ContactEmailChangeMail::class, fn (ContactEmailChangeMail $mail): bool => $mail->step === ContactEmailChangeMail::STEP_CONFIRM_NEW);
    }

    public function test_old_link_cannot_be_reused_to_skip_the_new_address(): void
    {
        $this->requestChange('neu@example.com');
        $oldToken = $this->tokenFrom(ContactEmailChangeMail::STEP_CONFIRM_OLD);

        $this->post("/email-aendern/{$oldToken}");
        $this->post("/email-aendern/{$oldToken}");
        $this->get("/email-aendern/{$oldToken}")->assertSee('Link nicht mehr gültig');

        $this->assertSame('alt@example.com', $this->contact->fresh()->email);
    }

    public function test_address_of_another_contact_cannot_be_used(): void
    {
        Contact::factory()->create(['email' => 'vergeben@example.com']);
        $this->actingAs($this->contact, 'contact');

        Livewire::test(Account::class)
            ->set('newEmail', 'vergeben@example.com')
            ->call('requestEmailChange')
            ->assertHasErrors('newEmail');

        Livewire::test(Account::class)
            ->set('newEmail', 'alt@example.com')
            ->call('requestEmailChange')
            ->assertHasErrors(['newEmail' => 'not_in']);

        Mail::assertNothingSent();
    }

    public function test_change_is_aborted_if_address_was_taken_in_the_meantime(): void
    {
        $this->requestChange('neu@example.com');
        $this->post('/email-aendern/'.$this->tokenFrom(ContactEmailChangeMail::STEP_CONFIRM_OLD));

        Contact::factory()->create(['email' => 'neu@example.com']);

        $newToken = $this->tokenFrom(ContactEmailChangeMail::STEP_CONFIRM_NEW);
        $this->post("/email-aendern/{$newToken}");
        $this->get("/email-aendern/{$newToken}")->assertSee('Adresse nicht verfügbar');
        $this->get("/email-aendern/{$newToken}")->assertSee('Link nicht mehr gültig');

        $this->assertSame('alt@example.com', $this->contact->fresh()->email);
    }

    public function test_links_expire_and_pending_change_can_be_cancelled(): void
    {
        $this->requestChange('neu@example.com');
        $token = $this->tokenFrom(ContactEmailChangeMail::STEP_CONFIRM_OLD);

        Livewire::test(Account::class)
            ->assertSee('neu@example.com')
            ->call('cancelEmailChange')
            ->assertDontSee('Fast fertig');

        $this->get("/email-aendern/{$token}")->assertSee('Link nicht mehr gültig');

        $this->requestChange('neu@example.com');
        $token = $this->tokenFrom(ContactEmailChangeMail::STEP_CONFIRM_OLD);
        $this->travel(25)->hours();

        $this->get("/email-aendern/{$token}")->assertSee('Link nicht mehr gültig');
    }

    public function test_change_in_cockpit_notifies_old_address_and_requires_new_confirmation(): void
    {
        $user = User::factory()->create();
        foreach (['ViewAny', 'View', 'Update'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:Contact"));
        }
        $this->actingAs($user);

        Livewire::test(EditContact::class, ['record' => $this->contact->id])
            ->fillForm(['email' => 'cockpit@example.com'])
            ->call('save')
            ->assertHasNoFormErrors();

        $contact = $this->contact->fresh();
        $this->assertSame('cockpit@example.com', $contact->email);
        $this->assertFalse($contact->hasVerifiedEmail());

        Mail::assertSent(ContactEmailChangeMail::class, fn (ContactEmailChangeMail $mail): bool => $mail->hasTo('alt@example.com')
            && $mail->step === ContactEmailChangeMail::STEP_CHANGED
            && $mail->newEmail === 'cockpit@example.com');
    }

    public function test_saving_without_email_change_sends_no_notice(): void
    {
        $this->contact->update(['name' => 'Anderer Name']);

        Mail::assertNothingSent();
    }
}
