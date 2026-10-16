<?php

namespace Tests\Feature\Messages;

use App\Filament\Resources\Messages\MessageResource;
use App\Filament\Resources\Messages\Pages\CreateMessage;
use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Filament\Resources\Messages\Pages\ViewMessage;
use App\Mail\MessageMail;
use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    protected User $sender;

    protected User $anna;

    protected User $ben;

    protected Group $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();

        $this->sender = $this->userWithMessagePermissions('Chefin');
        $this->anna = $this->userWithMessagePermissions('Anna');
        $this->ben = $this->userWithMessagePermissions('Ben');

        $this->team = Group::factory()->create(['name' => 'Team']);
        $this->team->users()->attach([$this->sender->id, $this->anna->id, $this->ben->id]);
    }

    protected function userWithMessagePermissions(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);

        foreach (['ViewAny', 'View', 'Create', 'Delete', 'DeleteAny'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:Message"));
        }

        return $user;
    }

    protected function messageToTeam(): Message
    {
        $message = Message::factory()->create(['user_id' => $this->sender->id]);
        $message->syncRecipients(['group:'.$this->team->id]);

        return $message;
    }

    public function test_message_can_be_sent_to_users_and_groups(): void
    {
        $outsider = $this->userWithMessagePermissions('Externe');
        $this->actingAs($this->sender);

        Livewire::test(CreateMessage::class)
            ->fillForm([
                'recipients' => ['group:'.$this->team->id, 'user:'.$outsider->id, 'user:'.$this->anna->id],
                'subject' => 'Teammeeting',
                'body' => '<p>Morgen um 10 Uhr.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Nachricht gesendet');

        $message = Message::query()->sole();

        $this->assertSame($this->sender->id, $message->user_id);
        $this->assertSame('Anna, Externe, Team', $message->recipientNames());
        $this->assertEqualsCanonicalizing(
            [$this->anna->id, $this->ben->id, $outsider->id],
            $message->receivers()->pluck('users.id')->all(),
            'Every reached user receives the message once, the sender not at all.',
        );

        Mail::assertSent(MessageMail::class, 3);
        Mail::assertNotSent(MessageMail::class, fn (MessageMail $mail): bool => $mail->hasTo($this->sender->email));
        $this->assertSame(1, $this->anna->notifications()->count());
        $this->assertSame(0, $this->sender->notifications()->count());
    }

    public function test_recipients_and_subject_are_required(): void
    {
        $this->actingAs($this->sender);

        Livewire::test(CreateMessage::class)
            ->fillForm(['recipients' => [], 'subject' => null])
            ->call('create')
            ->assertHasFormErrors(['recipients' => 'required', 'subject' => 'required']);

        $this->assertSame(0, Message::query()->count());
    }

    public function test_receivers_mark_messages_as_read_individually(): void
    {
        $message = $this->messageToTeam();
        $this->actingAs($this->anna);

        $this->assertSame('1', MessageResource::getNavigationBadge());

        Livewire::test(ListMessages::class)
            ->assertCanSeeTableRecords([$message])
            ->assertActionHidden(TestAction::make('markAsUnread')->table($message))
            ->callAction(TestAction::make('markAsRead')->table($message));

        $this->assertTrue($message->isReadBy($this->anna));
        $this->assertFalse($message->isReadBy($this->ben));
        $this->assertNull(MessageResource::getNavigationBadge());
        $this->assertSame('1/2', $message->readProgress());

        Livewire::test(ViewMessage::class, ['record' => $message->id])
            ->assertActionHidden('markAsRead')
            ->callAction('markAsUnread');

        $this->assertFalse($message->isReadBy($this->anna));
    }

    public function test_messages_can_be_marked_as_read_in_bulk(): void
    {
        $messages = [$this->messageToTeam(), $this->messageToTeam()];
        $this->actingAs($this->ben);

        Livewire::test(ListMessages::class)
            ->selectTableRecords($messages)
            ->callAction(TestAction::make('markAsRead')->table()->bulk());

        $this->assertSame(0, Message::query()->unreadBy($this->ben)->count());
        $this->assertSame(2, Message::query()->unreadBy($this->anna)->count());
    }

    public function test_tabs_separate_inbox_unread_and_sent_messages(): void
    {
        $received = $this->messageToTeam();
        $sent = Message::factory()->create(['user_id' => $this->anna->id]);
        $sent->syncRecipients(['user:'.$this->ben->id]);
        $this->actingAs($this->anna);

        Livewire::test(ListMessages::class)
            ->assertCanSeeTableRecords([$received])
            ->assertCanNotSeeTableRecords([$sent])
            ->set('activeTab', 'sent')
            ->assertCanSeeTableRecords([$sent])
            ->assertCanNotSeeTableRecords([$received])
            ->set('activeTab', 'unread')
            ->assertCanSeeTableRecords([$received]);
    }

    public function test_users_only_see_messages_they_sent_or_received(): void
    {
        $outsider = $this->userWithMessagePermissions('Externe');
        $message = $this->messageToTeam();
        $this->actingAs($outsider);

        Livewire::test(ListMessages::class)
            ->assertCanNotSeeTableRecords([$message]);

        $this->get(MessageResource::getUrl('view', ['record' => $message]))->assertNotFound();
    }

    public function test_only_the_sender_can_delete_a_message(): void
    {
        $message = $this->messageToTeam();

        $this->actingAs($this->anna);
        Livewire::test(ViewMessage::class, ['record' => $message->id])
            ->assertActionHidden('delete');

        $this->actingAs($this->sender);
        Livewire::test(ViewMessage::class, ['record' => $message->id])
            ->callAction('delete');

        $this->assertModelMissing($message);
    }

    public function test_reply_prefills_sender_and_subject(): void
    {
        $message = $this->messageToTeam();
        $this->actingAs($this->anna);

        Livewire::withQueryParams(['reply' => $message->id])
            ->test(CreateMessage::class)
            ->assertFormSet([
                'recipients' => ['user:'.$this->sender->id],
                'subject' => "Re: {$message->subject}",
            ]);
    }
}
