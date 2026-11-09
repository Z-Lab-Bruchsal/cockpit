<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use AlexanderGabriel\FilamentDashboardTabs\Concerns\HasDashboardTabs;
use App\Enums\TeamArea;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name', 'email', 'password', 'street', 'zip', 'city',
    'team_visible', 'team_area', 'team_role', 'team_bio', 'team_photo_path', 'team_photo_alt', 'team_sort', 'team_contactable',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar
{
    use HasDashboardTabs;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
    use HasRoles;

    /**
     * Only staff with a role or a direct permission may enter the cockpit,
     * so a bare account at the OAuth2 server is not enough.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->roles()->exists() || $this->permissions()->exists();
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
            'team_visible' => 'boolean',
            'team_area' => TeamArea::class,
            'team_sort' => 'integer',
            'team_contactable' => 'boolean',
        ];
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class);
    }

    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function chips(): HasMany
    {
        return $this->hasMany(Chip::class);
    }

    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class);
    }

    /**
     * Courses this user is responsible for.
     */
    public function responsibleCourses(): HasMany
    {
        return $this->hasMany(Course::class, 'responsible_user_id');
    }

    /**
     * Team members shown on the homepage, in their configured order.
     *
     * @param  Builder<User>  $query
     */
    public function scopeTeamMembers(Builder $query): void
    {
        $query
            ->where('team_visible', true)
            ->whereNotNull('team_area')
            ->orderBy('team_sort')
            ->orderBy('name');
    }

    /**
     * Whether visitors may send this team member a message via the homepage.
     */
    public function isContactableViaHomepage(): bool
    {
        return $this->team_visible && $this->team_area !== null && $this->team_contactable;
    }

    public function teamPhotoUrl(): ?string
    {
        return filled($this->team_photo_path) ? Storage::disk('public')->url($this->team_photo_path) : null;
    }

    /**
     * Up to two initials, used when no photo is set.
     */
    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)) ?: [])
            ->filter()
            ->reject(fn (string $part): bool => str_ends_with($part, '.'))
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->pipe(fn ($letters) => $letters->count() > 1 ? $letters->first().$letters->last() : (string) $letters->first());
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->teamPhotoUrl();
    }
}
