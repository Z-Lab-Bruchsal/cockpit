<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class);
    }

    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function timeProfileAssignments(): HasMany
    {
        return $this->hasMany(TimeProfileAssignment::class);
    }

    public function currentTimeProfile(?Carbon $onDate = null): ?TimeProfile
    {
        return TimeProfileAssignment::currentFor($this, $onDate);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles()->pluck('name')->toArray());
    }

    /**
     * IDs of all users visible to this user: themselves, plus every member of
     * a group that is attached to any role this user holds.
     *
     * @return array<int, int>
     */
    public function visibleUserIds(): array
    {
        $groupIds = $this->roles()->with('groups')->get()
            ->pluck('groups')
            ->flatten()
            ->pluck('id')
            ->unique();

        return User::query()
            ->where('id', $this->id)
            ->orWhereHas('groups', fn (Builder $query) => $query->whereIn('groups.id', $groupIds))
            ->pluck('id')
            ->toArray();
    }

    public function canManageTimeEntriesFor(User $target): bool
    {
        return $this->id === $target->id || in_array($target->id, $this->visibleUserIds(), true);
    }
}
