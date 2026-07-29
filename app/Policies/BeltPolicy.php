<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Belt;
use Illuminate\Auth\Access\HandlesAuthorization;

class BeltPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Belt');
    }

    public function view(AuthUser $authUser, Belt $belt): bool
    {
        return $authUser->can('View:Belt');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Belt');
    }

    public function update(AuthUser $authUser, Belt $belt): bool
    {
        return $authUser->can('Update:Belt');
    }

    public function delete(AuthUser $authUser, Belt $belt): bool
    {
        return $authUser->can('Delete:Belt');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Belt');
    }

    public function restore(AuthUser $authUser, Belt $belt): bool
    {
        return $authUser->can('Restore:Belt');
    }

    public function forceDelete(AuthUser $authUser, Belt $belt): bool
    {
        return $authUser->can('ForceDelete:Belt');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Belt');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Belt');
    }

    public function replicate(AuthUser $authUser, Belt $belt): bool
    {
        return $authUser->can('Replicate:Belt');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Belt');
    }

}