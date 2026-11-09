<?php

namespace Tests\Feature\MailTestMode;

use AlexanderGabriel\FilamentMailTestMode\MailTestMode;
use AlexanderGabriel\FilamentMailTestMode\Models\MailTestModeSetting;
use AlexanderGabriel\FilamentMailTestMode\Pages\MailTestModePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Message;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class MailTestModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_mails_go_to_real_recipients_when_test_mode_is_off(): void
    {
        $this->sendMail();

        $email = $this->lastSentEmail();
        $this->assertSame(['parent@example.com'], $this->addresses($email->getTo()));
        $this->assertSame(['cc@example.com'], $this->addresses($email->getCc()));
        $this->assertSame('Buchungsbestätigung', $email->getSubject());
        $this->assertFalse($email->getHeaders()->has('X-Original-To'));
    }

    public function test_mails_are_redirected_to_the_test_recipient(): void
    {
        app(MailTestMode::class)->enable('tester@example.com');

        $this->sendMail();

        $email = $this->lastSentEmail();
        $this->assertSame(['tester@example.com'], $this->addresses($email->getTo()));
        $this->assertSame([], $email->getCc());
        $this->assertSame([], $email->getBcc());
        $this->assertSame('[TEST] Buchungsbestätigung', $email->getSubject());
        $this->assertSame('parent@example.com', $email->getHeaders()->get('X-Original-To')->getBodyAsString());
        $this->assertSame('cc@example.com', $email->getHeaders()->get('X-Original-Cc')->getBodyAsString());
        $this->assertSame('bcc@example.com', $email->getHeaders()->get('X-Original-Bcc')->getBodyAsString());
    }

    public function test_subject_prefix_can_be_disabled(): void
    {
        config(['filament-mail-test-mode.subject_prefix' => null]);
        app(MailTestMode::class)->enable('tester@example.com');

        $this->sendMail();

        $this->assertSame('Buchungsbestätigung', $this->lastSentEmail()->getSubject());
    }

    public function test_env_config_forces_test_mode_and_overrides_database(): void
    {
        app(MailTestMode::class)->enable('db@example.com');
        app(MailTestMode::class)->disable();
        config([
            'filament-mail-test-mode.enabled' => true,
            'filament-mail-test-mode.recipient' => 'env@example.com',
        ]);

        $this->sendMail();

        $this->assertSame(['env@example.com'], $this->addresses($this->lastSentEmail()->getTo()));
    }

    public function test_mails_are_discarded_when_test_mode_has_no_recipient(): void
    {
        config(['filament-mail-test-mode.enabled' => true, 'filament-mail-test-mode.recipient' => null]);

        $this->sendMail();

        $this->assertCount(0, $this->sentMessages());
    }

    public function test_mail_notifications_are_redirected(): void
    {
        app(MailTestMode::class)->enable('tester@example.com');
        $user = User::factory()->create(['email' => 'real-user@example.com']);

        $user->notify(new class extends Notification
        {
            public function via(object $notifiable): array
            {
                return ['mail'];
            }

            public function toMail(object $notifiable): MailMessage
            {
                return (new MailMessage)->subject('Erinnerung')->line('Hallo');
            }
        });

        $email = $this->lastSentEmail();
        $this->assertSame(['tester@example.com'], $this->addresses($email->getTo()));
        $this->assertSame('[TEST] Erinnerung', $email->getSubject());
    }

    public function test_page_requires_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(MailTestModePage::getUrl())->assertForbidden();
    }

    public function test_test_mode_can_be_enabled_with_prefilled_own_address(): void
    {
        $user = $this->actingAsAuthorizedUser();

        Livewire::test(MailTestModePage::class)
            ->mountAction('enable')
            ->assertSchemaStateSet(['recipient' => $user->email], 'mountedActionSchema0')
            ->setActionData(['recipient' => 'qa@example.com'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified();

        $setting = MailTestModeSetting::current();
        $this->assertTrue($setting->enabled);
        $this->assertSame('qa@example.com', $setting->recipient);
        $this->assertSame($user->id, $setting->activated_by);
    }

    public function test_enabling_requires_a_valid_address(): void
    {
        $this->actingAsAuthorizedUser();

        Livewire::test(MailTestModePage::class)
            ->callAction('enable', ['recipient' => 'keine-adresse'])
            ->assertHasActionErrors(['recipient' => 'email']);

        $this->assertFalse(app(MailTestMode::class)->isEnabled());
    }

    public function test_test_mode_can_be_disabled(): void
    {
        $this->actingAsAuthorizedUser();
        app(MailTestMode::class)->enable('qa@example.com');

        Livewire::test(MailTestModePage::class)
            ->assertActionHidden('enable')
            ->callAction('disable')
            ->assertNotified();

        $this->assertFalse(app(MailTestMode::class)->isEnabled());
    }

    public function test_disable_is_hidden_when_forced_by_env(): void
    {
        $this->actingAsAuthorizedUser();
        config(['filament-mail-test-mode.enabled' => true, 'filament-mail-test-mode.recipient' => 'env@example.com']);

        Livewire::test(MailTestModePage::class)
            ->assertActionHidden('disable')
            ->assertActionHidden('enable');
    }

    public function test_test_mail_is_sent_to_the_test_recipient(): void
    {
        $this->actingAsAuthorizedUser();
        app(MailTestMode::class)->enable('qa@example.com');

        Livewire::test(MailTestModePage::class)->callAction('sendTestMail');

        $this->assertSame(['qa@example.com'], $this->addresses($this->lastSentEmail()->getTo()));
    }

    public function test_banner_is_shown_in_panel_only_while_active(): void
    {
        $this->actingAsAuthorizedUser();

        $this->get(MailTestModePage::getUrl())
            ->assertOk()
            ->assertDontSee('E-Mail-Testmodus aktiv – alle E-Mails gehen an');

        app(MailTestMode::class)->enable('qa@example.com');

        $this->get(MailTestModePage::getUrl())
            ->assertOk()
            ->assertSee('E-Mail-Testmodus aktiv – alle E-Mails gehen an qa@example.com');
    }

    protected function actingAsAuthorizedUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('View:MailTestModePage'));
        $this->actingAs($user);

        return $user;
    }

    protected function sendMail(): void
    {
        Mail::raw('Inhalt', function (Message $message): void {
            $message->to('parent@example.com')
                ->cc('cc@example.com')
                ->bcc('bcc@example.com')
                ->subject('Buchungsbestätigung');
        });
    }

    /**
     * @return array<int, SentMessage>
     */
    protected function sentMessages(): array
    {
        return iterator_to_array(app('mailer')->getSymfonyTransport()->messages());
    }

    protected function lastSentEmail(): Email
    {
        $messages = $this->sentMessages();
        $this->assertNotEmpty($messages, 'No mail was sent.');

        /** @var Email $email */
        $email = end($messages)->getOriginalMessage();

        return $email;
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, string>
     */
    protected function addresses(array $addresses): array
    {
        return array_map(fn (Address $address): string => $address->getAddress(), $addresses);
    }
}
