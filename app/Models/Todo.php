<?php

namespace App\Models;

use App\Mail\TodoMail;
use App\Observers\TodoObserver;
use App\Services\Recipients\Recipients;
use Database\Factories\TodoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Mail;

/**
 * A todo is assigned to any number of users and groups.
 *
 * By default the first assignee completing it completes it for everybody. If the creator enables
 * "individual_completion", every assignee completes it for themselves; it is done once all have.
 */
#[Fillable('name', 'content', 'user_id', 'due_date', 'done_date', 'follow_up', 'follow_up_notified_at', 'review', 'individual_completion')]
#[ObservedBy([TodoObserver::class])]
class Todo extends Model
{
    /** @use HasFactory<TodoFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'review' => 'boolean',
            'individual_completion' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedUsers(): MorphToMany
    {
        return $this->morphedByMany(User::class, 'assignee', 'todo_assignees');
    }

    public function assignedGroups(): MorphToMany
    {
        return $this->morphedByMany(Group::class, 'assignee', 'todo_assignees');
    }

    /**
     * Assignees who completed the todo for themselves (only used with individual completion).
     */
    public function completedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'todo_completions')->withTimestamps();
    }

    /**
     * Todos the given user created or is assigned to (directly or via a group).
     *
     * @param  Builder<Todo>  $query
     */
    public function scopeRelevantFor(Builder $query, User $user): void
    {
        $query->relevantForUsers([$user->id]);
    }

    /**
     * @param  Builder<Todo>  $query
     * @param  list<int>  $userIds
     */
    public function scopeRelevantForUsers(Builder $query, array $userIds): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereIn('user_id', $userIds)
            ->orWhereHas('assignedUsers', fn (Builder $users) => $users->whereIn('users.id', $userIds))
            ->orWhereHas('assignedGroups', fn (Builder $groups) => $groups->whereHas('users', fn (Builder $users) => $users->whereIn('users.id', $userIds))));
    }

    /**
     * Todos that are neither done for everybody nor completed by the given user.
     *
     * @param  Builder<Todo>  $query
     */
    public function scopeOpenFor(Builder $query, User $user): void
    {
        $query->whereNull('done_date')
            ->whereDoesntHave('completedBy', fn (Builder $users) => $users->whereKey($user->id));
    }

    /**
     * @return list<string>
     */
    public function recipientKeys(): array
    {
        return Recipients::keys(
            $this->assignedUsers()->pluck('users.id')->all(),
            $this->assignedGroups()->pluck('groups.id')->all(),
        );
    }

    /**
     * Replaces the assignment and informs every user who is newly reached by it.
     *
     * @param  array<int, string>  $keys
     */
    public function syncRecipients(array $keys): void
    {
        $previousUserIds = $this->wasRecentlyCreated ? [] : $this->assignees()->modelKeys();

        $recipients = Recipients::parse($keys);
        $this->assignedUsers()->sync($recipients['users']);
        $this->assignedGroups()->sync($recipients['groups']);

        [$subject, $message] = $this->wasRecentlyCreated
            ? ['Neue Todo', 'Es gibt eine neue Todo für dich:']
            : ['Todo zugewiesen', 'Dir wurde eine bestehende Todo zugewiesen:'];

        foreach ($this->assignees()->whereNotIn('id', $previousUserIds) as $user) {
            Mail::to($user)->send(new TodoMail($this, $subject, $message));
        }
    }

    /**
     * Every user the todo is assigned to, directly or via one of its groups.
     *
     * @return Collection<int, User>
     */
    public function assignees(): Collection
    {
        return Recipients::resolveUsers(
            $this->assignedUsers()->pluck('users.id')->all(),
            $this->assignedGroups()->pluck('groups.id')->all(),
        );
    }

    /**
     * Names of the assigned users and groups, e.g. "Anna, Team Kurse".
     */
    public function assigneeNames(): string
    {
        return $this->assignedUsers->pluck('name')
            ->merge($this->assignedGroups->pluck('name'))
            ->join(', ');
    }

    public function isAssignee(User $user): bool
    {
        return $this->assignedUsers()->whereKey($user->id)->exists()
            || $this->assignedGroups()->whereHas('users', fn (Builder $users) => $users->whereKey($user->id))->exists();
    }

    public function isOwnerOrAssignee(User $user): bool
    {
        return $this->user_id === $user->id || $this->isAssignee($user);
    }

    public function isDoneFor(User $user): bool
    {
        return $this->done_date !== null
            || ($this->individual_completion && $this->completedBy()->whereKey($user->id)->exists());
    }

    /**
     * Completes the todo for the given user. With individual completion an assignee only completes
     * their own part; the todo is done for everybody once all assignees have completed it.
     * Anybody else (e.g. the creator) completes it for everybody.
     */
    public function markDoneBy(User $user): void
    {
        if (! $this->individual_completion || ! $this->isAssignee($user)) {
            $this->update(['done_date' => today()]);

            return;
        }

        $this->completedBy()->syncWithoutDetaching([$user->id]);

        $openAssigneeIds = array_diff($this->assignees()->modelKeys(), $this->completedBy()->pluck('users.id')->all());

        if ($openAssigneeIds === []) {
            $this->update(['done_date' => today()]);
        }
    }

    /**
     * Reopens the todo. With individual completion an assignee who completed it only reopens their own part.
     *
     * @return bool whether the todo was reopened for everybody
     */
    public function reopenFor(User $user): bool
    {
        if ($this->individual_completion && $this->completedBy()->whereKey($user->id)->exists()) {
            $this->completedBy()->detach($user->id);
            $this->update(['done_date' => null]);

            return false;
        }

        $this->completedBy()->detach();
        $this->update(['done_date' => null]);

        return true;
    }

    /**
     * Completion progress for individual completion, e.g. "2/3".
     */
    public function completionProgress(): ?string
    {
        if (! $this->individual_completion) {
            return null;
        }

        $assigneeIds = $this->assignees()->modelKeys();
        $completedCount = $this->completedBy()->whereIn('users.id', $assigneeIds)->count();

        return "{$completedCount}/".count($assigneeIds);
    }
}
