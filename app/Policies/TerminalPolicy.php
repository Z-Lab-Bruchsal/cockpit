<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Terminal;
use Illuminate\Auth\Access\HandlesAuthorization;

class TerminalPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Terminal');
    }

    public function view(AuthUser $authUser, Terminal $terminal): bool
    {
        return $authUser->can('View:Terminal');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Terminal');
    }

    public function update(AuthUser $authUser, Terminal $terminal): bool
    {
        return $authUser->can('Update:Terminal');
    }

    public function delete(AuthUser $authUser, Terminal $terminal): bool
    {
        return $authUser->can('Delete:Terminal');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Terminal');
    }

    public function restore(AuthUser $authUser, Terminal $terminal): bool
    {
        return $authUser->can('Restore:Terminal');
    }

    public function forceDelete(AuthUser $authUser, Terminal $terminal): bool
    {
        return $authUser->can('ForceDelete:Terminal');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Terminal');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Terminal');
    }

    public function replicate(AuthUser $authUser, Terminal $terminal): bool
    {
        return $authUser->can('Replicate:Terminal');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Terminal');
    }

}