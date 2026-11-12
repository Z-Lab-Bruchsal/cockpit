<?php

namespace App\Models;

use App\Enums\TeamArea;
use Database\Factories\TeamProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'user_id', 'name', 'team_visible', 'team_area', 'team_role', 'team_bio',
    'team_photo_path', 'team_photo_alt', 'team_sort', 'team_contactable',
])]
class TeamProfile extends Model
{
    /** @use HasFactory<TeamProfileFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'team_visible' => 'boolean',
            'team_area' => TeamArea::class,
            'team_sort' => 'integer',
            'team_contactable' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class);
    }

    public function displayName(): string
    {
        return $this->user?->name ?? (string) $this->name;
    }

    public function publicId(): string
    {
        return $this->user_id === null ? 'profil-'.$this->id : (string) $this->user_id;
    }

    public function isContactableViaHomepage(): bool
    {
        return $this->team_visible && $this->team_area !== null && $this->team_contactable && filled($this->user?->email);
    }

    public function teamPhotoUrl(): ?string
    {
        return filled($this->team_photo_path) ? Storage::disk('public')->url($this->team_photo_path) : null;
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->displayName())) ?: [])
            ->filter()
            ->reject(fn (string $part): bool => str_ends_with($part, '.'))
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->pipe(fn ($letters) => $letters->count() > 1 ? $letters->first().$letters->last() : (string) $letters->first());
    }
}
