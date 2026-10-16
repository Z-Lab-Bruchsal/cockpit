<?php

namespace App\Models;

use App\Filament\Resources\Messages\MessageResource;
use App\Mail\MessageMail;
use App\Services\Recipients\Recipients;
use Database\Factories\MessageFactory;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Mail;

/**
 * An internal message to users and groups. The selected users and groups are kept as written,
 * every user reached by them receives the message once and marks it as read on their own.
 */
#[Fillable('user_id', 'subject', 'body')]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recipientUsers(): MorphToMany
    {
        return $this->morphedByMany(User::class, 'recipient', 'message_recipients');
    }

    public function recipientGroups(): MorphToMany
    {
        return $this->morphedByMany(Group::class, 'recipient', 'message_recipients');
    }

    /**
     * Users who received the message, with their read status.
     */
    public function receivers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('read_at')->withTimestamps();
    }

    /**
     * Messages the given user sent or received.
     *
     * @param  Builder<Message>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('user_id', $user->id)
            ->orWhereHas('receivers', fn (Builder $receivers) => $receivers->whereKey($user->id)));
    }

    /**
     * @param  Builder<Message>  $query
     */
    public function scopeReceivedBy(Builder $query, User $user): void
    {
        $query->whereHas('receivers', fn (Builder $receivers) => $receivers->whereKey($user->id));
    }

    /**
     * @param  Builder<Message>  $query
     */
    public function scopeUnreadBy(Builder $query, User $user): void
    {
        $query->whereHas('receivers', fn (Builder $receivers) => $receivers->whereKey($user->id)->whereNull('message_user.read_at'));
    }

    /**
     * @param  Builder<Message>  $query
     */
    public function scopeSentBy(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    /**
     * @return list<string>
     */
    public function recipientKeys(): array
    {
        return Recipients::keys(
            $this->recipientUsers()->pluck('users.id')->all(),
            $this->recipientGroups()->pluck('groups.id')->all(),
        );
    }

    /**
     * Stores the selected recipients and delivers the message to every user reached by them
     * (except the sender), notifying each of them in the cockpit and by mail.
     *
     * @param  array<int, string>  $keys
     */
    public function syncRecipients(array $keys): void
    {
        $recipients = Recipients::parse($keys);
        $this->recipientUsers()->sync($recipients['users']);
        $this->recipientGroups()->sync($recipients['groups']);

        $alreadyReceivedIds = $this->receivers()->pluck('users.id')->all();

        $newReceivers = Recipients::resolveUsers($recipients['users'], $recipients['groups'])
            ->reject(fn (User $user): bool => $user->id === $this->user_id || in_array($user->id, $alreadyReceivedIds, true));

        $this->receivers()->attach($newReceivers->modelKeys());

        foreach ($newReceivers as $receiver) {
            $this->notify($receiver);
        }
    }

    public function isReceivedBy(User $user): bool
    {
        return $this->receivers()->whereKey($user->id)->exists();
    }

    public function isReadBy(User $user): bool
    {
        return $this->receivers()->whereKey($user->id)->whereNotNull('message_user.read_at')->exists();
    }

    public function markAsReadBy(User $user): void
    {
        $this->receivers()->updateExistingPivot($user->id, ['read_at' => now()]);
    }

    public function markAsUnreadBy(User $user): void
    {
        $this->receivers()->updateExistingPivot($user->id, ['read_at' => null]);
    }

    /**
     * Names of the selected users and groups, e.g. "Anna, Team Kurse".
     */
    public function recipientNames(): string
    {
        return $this->recipientUsers->pluck('name')
            ->merge($this->recipientGroups->pluck('name'))
            ->join(', ');
    }

    /**
     * Read progress of the receivers, e.g. "2/3".
     */
    public function readProgress(): string
    {
        return $this->receivers()->whereNotNull('message_user.read_at')->count().'/'.$this->receivers()->count();
    }

    protected function notify(User $receiver): void
    {
        Notification::make()
            ->title("Neue Nachricht von {$this->sender->name}")
            ->body($this->subject)
            ->icon('heroicon-o-envelope')
            ->actions([
                Action::make('view')
                    ->label('Lesen')
                    ->url(MessageResource::getUrl('view', ['record' => $this], panel: 'admin'))
                    ->markAsRead(),
            ])
            ->sendToDatabase($receiver);

        Mail::to($receiver)->send(new MessageMail($this));
    }
}
