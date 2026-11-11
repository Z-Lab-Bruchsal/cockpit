<?php

namespace Tests\Feature\Messages;

use App\Filament\Clusters\Settings\Pages\CockpitPage;
use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Filament\Resources\Messages\Pages\ViewMessage;
use App\Mail\MessageMail;
use App\Models\CompanySetting;
use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use App\Services\Messages\InboundMailImporter;
use App\Services\Recipients\Recipients;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InboundMailImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $anna;

    protected User $ben;

    protected Group $office;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();
        config(['mail.from.address' => 'cockpit@example.com']);

        $this->anna = User::factory()->create(['name' => 'Anna']);
        $this->ben = User::factory()->create(['name' => 'Ben']);

        foreach ([$this->anna, $this->ben] as $user) {
            foreach (['ViewAny', 'View', 'Create', 'Delete'] as $ability) {
                $user->givePermissionTo(Permission::findOrCreate("{$ability}:Message"));
            }
        }

        $this->office = Group::factory()->create(['name' => 'Büro']);
        $this->office->users()->attach([$this->anna->id, $this->ben->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{message_id: ?string, from_email: ?string, from_name: ?string, subject: ?string, text: ?string, html: ?string, attachment_count: int, auto_submitted: ?string, precedence: ?string}
     */
    protected function mail(array $overrides = []): array
    {
        return [
            'message_id' => 'abc123@mail.example.com',
            'from_email' => 'Maria@Example.com',
            'from_name' => 'Maria Muster',
            'subject' => 'Re: Raumänderung',
            'text' => "Danke für die Info!\n\nViele Grüße\nMaria",
            'html' => null,
            'attachment_count' => 0,
            'auto_submitted' => null,
            'precedence' => null,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function import(array $overrides = []): ?Message
    {
        return app(InboundMailImporter::class)->importMessage($this->mail($overrides), [Recipients::key(Recipients::GROUP, $this->office->id)]);
    }

    public function test_mail_becomes_an_internal_message_for_the_configured_recipients(): void
    {
        $message = $this->import();

        $this->assertNotNull($message);
        $this->assertNull($message->user_id);
        $this->assertTrue($message->isExternal());
        $this->assertSame('maria@example.com', $message->sender_email);
        $this->assertSame('Maria Muster <maria@example.com>', $message->senderName());
        $this->assertSame('abc123@mail.example.com', $message->external_message_id);
        $this->assertSame('Re: Raumänderung', $message->subject);
        $this->assertStringContainsString('Danke für die Info!', $message->body);
        $this->assertEqualsCanonicalizing([$this->anna->id, $this->ben->id], $message->receivers()->pluck('users.id')->all());

        $this->assertSame(1, $this->anna->notifications()->count());
        Mail::assertSent(MessageMail::class, 2);
        Mail::assertSent(MessageMail::class, fn (MessageMail $mail): bool => $mail->hasTo($this->anna->email) && $mail->envelope()->subject === 'Neue E-Mail: Re: Raumänderung');
    }

    public function test_the_same_mail_is_imported_only_once(): void
    {
        $this->assertNotNull($this->import());
        $this->assertNull($this->import(['message_id' => '<abc123@mail.example.com>']));

        $this->assertSame(1, Message::query()->count());
    }

    public function test_mails_without_message_id_are_deduplicated_by_content(): void
    {
        $this->assertNotNull($this->import(['message_id' => null]));
        $this->assertNull($this->import(['message_id' => null]));
        $this->assertNotNull($this->import(['message_id' => null, 'text' => 'Andere Mail']));

        $this->assertSame(2, Message::query()->count());
    }

    public function test_own_address_and_automatic_mails_are_skipped(): void
    {
        $this->assertNull($this->import(['message_id' => '1', 'from_email' => 'COCKPIT@example.com']));
        $this->assertNull($this->import(['message_id' => '2', 'auto_submitted' => 'auto-replied']));
        $this->assertNull($this->import(['message_id' => '3', 'precedence' => 'bulk']));
        $this->assertNull($this->import(['message_id' => '4', 'from_email' => null]));
        $this->assertNotNull($this->import(['message_id' => '5', 'auto_submitted' => 'no']));

        $this->assertSame(1, Message::query()->count());
    }

    public function test_html_only_mail_is_stored_as_escaped_text(): void
    {
        $message = $this->import([
            'text' => null,
            'html' => '<html><head><style>p{color:red}</style></head><body><p>Hallo <b>Team</b></p><script>alert(1)</script><p>&lt;img src=x onerror=alert(1)&gt;</p></body></html>',
        ]);

        $this->assertStringContainsString('Hallo Team', $message->body);
        $this->assertStringNotContainsString('<b>', $message->body);
        $this->assertStringNotContainsString('<script', $message->body);
        $this->assertStringNotContainsString('color:red', $message->body);
        $this->assertStringNotContainsString('<img', $message->body);
        $this->assertStringContainsString('&lt;img', $message->body);
    }

    public function test_attachments_are_noted_and_missing_subject_gets_a_placeholder(): void
    {
        $message = $this->import(['subject' => '', 'attachment_count' => 2]);

        $this->assertSame('(kein Betreff)', $message->subject);
        $this->assertStringContainsString('2 Anhänge wurden nicht übernommen', $message->body);
    }

    public function test_nothing_is_imported_without_recipients(): void
    {
        $this->assertNull(app(InboundMailImporter::class)->importMessage($this->mail(), []));
        $this->assertSame(0, app(InboundMailImporter::class)->import());

        $this->assertSame(0, Message::query()->count());
    }

    public function test_command_does_nothing_without_mailbox(): void
    {
        config(['imap.inbound.enabled' => false]);

        $this->artisan('app:import-inbound-mails')
            ->expectsOutputToContain('Kein Cockpit-Postfach eingerichtet')
            ->assertSuccessful();
    }

    public function test_external_message_is_shown_in_the_cockpit(): void
    {
        $message = $this->import();
        $this->actingAs($this->anna);

        Livewire::test(ListMessages::class)
            ->assertCanSeeTableRecords([$message])
            ->assertSee('Maria Muster');

        Livewire::test(ViewMessage::class, ['record' => $message->id])
            ->assertSee('Maria Muster &lt;maria@example.com&gt;', false)
            ->assertSee('Danke für die Info!')
            ->assertActionVisible('reply')
            ->assertActionHasUrl('reply', 'mailto:maria@example.com?subject='.rawurlencode('Re: Raumänderung'));
    }

    public function test_recipients_can_be_set_in_the_settings(): void
    {
        $this->seed(ShieldSeeder::class);
        $boss = User::factory()->create();
        $boss->assignRole('Z-Lab-Boss');
        $this->actingAs($boss);

        $key = Recipients::key(Recipients::GROUP, $this->office->id);

        Livewire::test(CockpitPage::class)
            ->fillForm(['inbound_mail_recipients' => [$key]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$key], CompanySetting::current()->inbound_mail_recipients);
    }
}
