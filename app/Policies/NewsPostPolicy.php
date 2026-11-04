<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NewsPost;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class NewsPostPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:NewsPost');
    }

    public function view(AuthUser $authUser, NewsPost $newsPost): bool
    {
        return $authUser->can('View:NewsPost');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:NewsPost');
    }

    public function update(AuthUser $authUser, NewsPost $newsPost): bool
    {
        return $authUser->can('Update:NewsPost');
    }

    public function delete(AuthUser $authUser, NewsPost $newsPost): bool
    {
        return $authUser->can('Delete:NewsPost');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:NewsPost');
    }

    public function restore(AuthUser $authUser, NewsPost $newsPost): bool
    {
        return $authUser->can('Restore:NewsPost');
    }

    public function forceDelete(AuthUser $authUser, NewsPost $newsPost): bool
    {
        return $authUser->can('ForceDelete:NewsPost');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:NewsPost');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:NewsPost');
    }

    public function replicate(AuthUser $authUser, NewsPost $newsPost): bool
    {
        return $authUser->can('Replicate:NewsPost');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:NewsPost');
    }
}
