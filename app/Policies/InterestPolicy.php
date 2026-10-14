<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Interest;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class InterestPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Interest');
    }

    public function view(AuthUser $authUser, Interest $interest): bool
    {
        return $authUser->can('View:Interest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Interest');
    }

    public function update(AuthUser $authUser, Interest $interest): bool
    {
        return $authUser->can('Update:Interest');
    }

    public function delete(AuthUser $authUser, Interest $interest): bool
    {
        return $authUser->can('Delete:Interest');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Interest');
    }

    public function restore(AuthUser $authUser, Interest $interest): bool
    {
        return $authUser->can('Restore:Interest');
    }

    public function forceDelete(AuthUser $authUser, Interest $interest): bool
    {
        return $authUser->can('ForceDelete:Interest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Interest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Interest');
    }

    public function replicate(AuthUser $authUser, Interest $interest): bool
    {
        return $authUser->can('Replicate:Interest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Interest');
    }
}
