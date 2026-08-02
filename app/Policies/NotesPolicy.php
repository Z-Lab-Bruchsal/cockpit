<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Notes;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class NotesPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Notes');
    }

    public function view(AuthUser $authUser, Notes $notes): bool
    {
        return $authUser->can('View:Notes');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Notes');
    }

    public function update(AuthUser $authUser, Notes $notes): bool
    {
        return $authUser->can('Update:Notes');
    }

    public function delete(AuthUser $authUser, Notes $notes): bool
    {
        return $authUser->can('Delete:Notes');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Notes');
    }

    public function restore(AuthUser $authUser, Notes $notes): bool
    {
        return $authUser->can('Restore:Notes');
    }

    public function forceDelete(AuthUser $authUser, Notes $notes): bool
    {
        return $authUser->can('ForceDelete:Notes');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Notes');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Notes');
    }

    public function replicate(AuthUser $authUser, Notes $notes): bool
    {
        return $authUser->can('Replicate:Notes');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Notes');
    }
}
