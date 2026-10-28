<?php

namespace Tests\Feature\Todos;

use App\Filament\Resources\Todos\Pages\CreateTodo;
use App\Filament\Resources\Todos\Pages\EditTodo;
use App\Filament\Resources\Todos\Pages\ListTodos;
use App\Mail\TodoMail;
use App\Models\Group;
use App\Models\Todo;
use App\Models\User;
use App\Services\Recipients\Recipients;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TodoAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $creator;

    protected User $anna;

    protected User $ben;

    protected Group $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();

        $this->creator = $this->userWithTodoPermissions('Chefin');
        $this->anna = $this->userWithTodoPermissions('Anna');
        $this->ben = $this->userWithTodoPermissions('Ben');

        $this->team = Group::factory()->create(['name' => 'Team']);
        $this->team->users()->attach([$this->anna->id, $this->ben->id]);
    }

    protected function userWithTodoPermissions(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);

        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:Todo"));
        }

        return $user;
    }

    protected function todoForTeam(bool $individualCompletion): Todo
    {
        $todo = Todo::factory()->create([
            'user_id' => $this->creator->id,
            'individual_completion' => $individualCompletion,
        ]);
        $todo->assignedGroups()->attach($this->team);

        return $todo;
    }

    public function test_recipient_options_list_users_and_groups(): void
    {
        $options = Recipients::options();

        $this->assertSame('Anna', $options['Personen']['user:'.$this->anna->id]);
        $this->assertStringStartsWith('Team', $options['Gruppen']['group:'.$this->team->id]);
    }

    public function test_todo_can_be_assigned_to_users_and_groups_at_once(): void
    {
        $other = User::factory()->create();
        $this->actingAs($this->creator);

        Livewire::test(CreateTodo::class)
            ->fillForm([
                'name' => 'Lager aufräumen',
                'recipients' => ['user:'.$other->id, 'group:'.$this->team->id],
                'individual_completion' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $todo = Todo::query()->sole();

        $this->assertTrue($todo->individual_completion);
        $this->assertEqualsCanonicalizing([$other->id], $todo->assignedUsers->modelKeys());
        $this->assertEqualsCanonicalizing([$this->team->id], $todo->assignedGroups->modelKeys());
        $this->assertEqualsCanonicalizing([$other->id, $this->anna->id, $this->ben->id], $todo->assignees()->modelKeys());

        Mail::assertSent(TodoMail::class, 3);
        Mail::assertSent(TodoMail::class, fn (TodoMail $mail): bool => $mail->hasTo($other->email) && $mail->mail_subject === 'Neue Todo');
    }

    public function test_only_newly_assigned_users_are_informed_when_the_assignment_changes(): void
    {
        $todo = Todo::factory()->create(['user_id' => $this->creator->id]);
        $todo->assignedUsers()->attach($this->anna);
        $this->actingAs($this->creator);

        Livewire::test(EditTodo::class, ['record' => $todo->id])
            ->assertFormSet(['recipients' => ['user:'.$this->anna->id]])
            ->fillForm(['recipients' => ['group:'.$this->team->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([], $todo->assignedUsers()->pluck('users.id')->all());
        Mail::assertSent(TodoMail::class, 1);
        Mail::assertSent(TodoMail::class, fn (TodoMail $mail): bool => $mail->hasTo($this->ben->email) && $mail->mail_subject === 'Todo zugewiesen');
    }

    public function test_shared_todo_is_done_for_everybody_once_one_assignee_completes_it(): void
    {
        $todo = $this->todoForTeam(individualCompletion: false);

        $todo->markDoneBy($this->anna);

        $this->assertNotNull($todo->fresh()->done_date);
        $this->assertTrue($todo->isDoneFor($this->ben));
        $this->assertSame(0, Todo::query()->openFor($this->ben)->count());
    }

    public function test_individual_todo_is_only_done_for_the_assignee_who_completed_it(): void
    {
        $todo = $this->todoForTeam(individualCompletion: true);

        $todo->markDoneBy($this->anna);

        $todo->refresh();
        $this->assertNull($todo->done_date);
        $this->assertTrue($todo->isDoneFor($this->anna));
        $this->assertFalse($todo->isDoneFor($this->ben));
        $this->assertSame(0, Todo::query()->openFor($this->anna)->count());
        $this->assertSame(1, Todo::query()->openFor($this->ben)->count());
        $this->assertSame('1/2', $todo->completionProgress());
        Mail::assertNothingSent();
    }

    public function test_individual_todo_is_done_once_every_assignee_completed_it(): void
    {
        $todo = $this->todoForTeam(individualCompletion: true);

        $todo->markDoneBy($this->anna);
        $todo->markDoneBy($this->ben);

        $this->assertNotNull($todo->fresh()->done_date);
        Mail::assertSent(TodoMail::class, fn (TodoMail $mail): bool => $mail->hasTo($this->creator->email) && $mail->mail_subject === 'Todo erledigt');
    }

    public function test_creator_who_is_not_assigned_completes_an_individual_todo_for_everybody(): void
    {
        $todo = $this->todoForTeam(individualCompletion: true);

        $todo->markDoneBy($this->creator);

        $this->assertNotNull($todo->fresh()->done_date);
    }

    public function test_assignee_reopens_an_individual_todo_only_for_themselves(): void
    {
        $todo = $this->todoForTeam(individualCompletion: true);
        $todo->markDoneBy($this->anna);
        $todo->markDoneBy($this->ben);

        $reopenedForAll = $todo->reopenFor($this->anna);

        $todo->refresh();
        $this->assertFalse($reopenedForAll);
        $this->assertNull($todo->done_date);
        $this->assertFalse($todo->isDoneFor($this->anna));
        $this->assertTrue($todo->isDoneFor($this->ben));
    }

    public function test_done_action_in_the_table_completes_an_individual_todo_only_for_the_current_user(): void
    {
        $todo = $this->todoForTeam(individualCompletion: true);
        $this->actingAs($this->anna);

        Livewire::test(ListTodos::class)
            ->assertCanSeeTableRecords([$todo])
            ->callAction(TestAction::make('done')->table($todo))
            ->assertCanNotSeeTableRecords([$todo]);

        $this->assertTrue($todo->isDoneFor($this->anna));
        $this->assertFalse($todo->isDoneFor($this->ben));

        $this->actingAs($this->ben);

        Livewire::test(ListTodos::class)
            ->assertCanSeeTableRecords([$todo])
            ->assertActionVisible(TestAction::make('done')->table($todo));
    }

    public function test_reopen_action_reopens_an_individual_todo_for_the_current_user(): void
    {
        $todo = $this->todoForTeam(individualCompletion: true);
        $todo->markDoneBy($this->anna);
        $this->actingAs($this->anna);

        Livewire::test(EditTodo::class, ['record' => $todo->id])
            ->assertActionHidden('done')
            ->callAction('reopen');

        $this->assertFalse($todo->isDoneFor($this->anna));
        Mail::assertNothingSent();
    }

    public function test_only_the_creator_can_change_the_completion_mode(): void
    {
        $todo = $this->todoForTeam(individualCompletion: false);

        $this->actingAs($this->anna);
        Livewire::test(EditTodo::class, ['record' => $todo->id])
            ->assertFormFieldDisabled('individual_completion');

        $this->actingAs($this->creator);
        Livewire::test(EditTodo::class, ['record' => $todo->id])
            ->assertFormFieldEnabled('individual_completion')
            ->fillForm(['individual_completion' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($todo->fresh()->individual_completion);
    }

    public function test_creator_cannot_be_forged(): void
    {
        $this->actingAs($this->creator);

        Livewire::test(CreateTodo::class)
            ->fillForm([
                'name' => 'Gefälscht',
                'user_id' => $this->anna->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($this->creator->id, Todo::query()->sole()->user_id);
    }

    public function test_rich_text_in_todo_mail_is_sanitized(): void
    {
        $todo = Todo::factory()->create([
            'user_id' => $this->creator->id,
            'content' => '<p>Bitte erledigen</p><script>alert(1)</script>',
        ]);

        $html = (new TodoMail($todo, 'Betreff', 'Hallo'))->render();

        $this->assertStringContainsString('Bitte erledigen', $html);
        $this->assertStringNotContainsString('<script', $html);
    }
}
