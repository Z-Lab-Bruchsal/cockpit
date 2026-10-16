<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Message;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Messages are only visible to their sender and receivers, can't be edited after sending
 * and can only be deleted by their sender.
 */
class MessagePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Message');
    }

    public function view(AuthUser $authUser, Message $message): bool
    {
        return $authUser->can('View:Message')
            && ($message->user_id === $authUser->getKey() || $message->receivers()->whereKey($authUser->getKey())->exists());
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Message');
    }

    public function update(AuthUser $authUser, Message $message): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, Message $message): bool
    {
        return $authUser->can('Delete:Message') && $message->user_id === $authUser->getKey();
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Message');
    }

    public function restore(AuthUser $authUser, Message $message): bool
    {
        return $authUser->can('Restore:Message');
    }

    public function forceDelete(AuthUser $authUser, Message $message): bool
    {
        return $authUser->can('ForceDelete:Message');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Message');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Message');
    }

    public function replicate(AuthUser $authUser, Message $message): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Message');
    }
}
