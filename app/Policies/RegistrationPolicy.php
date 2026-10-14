<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Registration;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RegistrationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Registration');
    }

    public function view(AuthUser $authUser, Registration $registration): bool
    {
        return $authUser->can('View:Registration');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Registration');
    }

    public function update(AuthUser $authUser, Registration $registration): bool
    {
        return $authUser->can('Update:Registration');
    }

    public function delete(AuthUser $authUser, Registration $registration): bool
    {
        return $authUser->can('Delete:Registration');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Registration');
    }

    public function restore(AuthUser $authUser, Registration $registration): bool
    {
        return $authUser->can('Restore:Registration');
    }

    public function forceDelete(AuthUser $authUser, Registration $registration): bool
    {
        return $authUser->can('ForceDelete:Registration');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Registration');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Registration');
    }

    public function replicate(AuthUser $authUser, Registration $registration): bool
    {
        return $authUser->can('Replicate:Registration');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Registration');
    }
}
