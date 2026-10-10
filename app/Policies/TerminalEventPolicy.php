<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TerminalEvent;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TerminalEventPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TerminalEvent');
    }

    public function view(AuthUser $authUser, TerminalEvent $terminalEvent): bool
    {
        return $authUser->can('View:TerminalEvent');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TerminalEvent');
    }

    public function update(AuthUser $authUser, TerminalEvent $terminalEvent): bool
    {
        return $authUser->can('Update:TerminalEvent');
    }

    public function delete(AuthUser $authUser, TerminalEvent $terminalEvent): bool
    {
        return $authUser->can('Delete:TerminalEvent');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TerminalEvent');
    }

    public function restore(AuthUser $authUser, TerminalEvent $terminalEvent): bool
    {
        return $authUser->can('Restore:TerminalEvent');
    }

    public function forceDelete(AuthUser $authUser, TerminalEvent $terminalEvent): bool
    {
        return $authUser->can('ForceDelete:TerminalEvent');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TerminalEvent');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TerminalEvent');
    }

    public function replicate(AuthUser $authUser, TerminalEvent $terminalEvent): bool
    {
        return $authUser->can('Replicate:TerminalEvent');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TerminalEvent');
    }
}
