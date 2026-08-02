<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Kid;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class KidPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Kid');
    }

    public function view(AuthUser $authUser, Kid $kid): bool
    {
        return $authUser->can('View:Kid');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Kid');
    }

    public function update(AuthUser $authUser, Kid $kid): bool
    {
        return $authUser->can('Update:Kid');
    }

    public function delete(AuthUser $authUser, Kid $kid): bool
    {
        return $authUser->can('Delete:Kid');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Kid');
    }

    public function restore(AuthUser $authUser, Kid $kid): bool
    {
        return $authUser->can('Restore:Kid');
    }

    public function forceDelete(AuthUser $authUser, Kid $kid): bool
    {
        return $authUser->can('ForceDelete:Kid');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Kid');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Kid');
    }

    public function replicate(AuthUser $authUser, Kid $kid): bool
    {
        return $authUser->can('Replicate:Kid');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Kid');
    }
}
