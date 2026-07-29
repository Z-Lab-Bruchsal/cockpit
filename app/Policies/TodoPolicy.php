<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Todo;
use Illuminate\Auth\Access\HandlesAuthorization;

class TodoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Todo');
    }

    public function view(AuthUser $authUser, Todo $todo): bool
    {
        return $authUser->can('View:Todo');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Todo');
    }

    public function update(AuthUser $authUser, Todo $todo): bool
    {
        return $authUser->can('Update:Todo');
    }

    public function delete(AuthUser $authUser, Todo $todo): bool
    {
        return $authUser->can('Delete:Todo');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Todo');
    }

    public function restore(AuthUser $authUser, Todo $todo): bool
    {
        return $authUser->can('Restore:Todo');
    }

    public function forceDelete(AuthUser $authUser, Todo $todo): bool
    {
        return $authUser->can('ForceDelete:Todo');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Todo');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Todo');
    }

    public function replicate(AuthUser $authUser, Todo $todo): bool
    {
        return $authUser->can('Replicate:Todo');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Todo');
    }

}