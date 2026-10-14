<?php

namespace App\Models;

use Database\Factories\KidFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * The birthdate is hidden from serialization so it never reaches the cockpit (e.g. in Livewire snapshots);
 * the cockpit only shows the age, the exact birthdate is for the parents.
 */
#[Hidden(['birthdate'])]
class Kid extends Model
{
    /** @use HasFactory<KidFactory> */
    use HasFactory;

    protected $fillable = ['name', 'email', 'birthdate', 'birthdate_estimated', 'notes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'birthdate_estimated' => 'boolean',
        ];
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class)->withPivot('date');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class);
    }

    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class);
    }

    /**
     * The birthdate as entered by the parents; null if unknown or only estimated from an age entered in the cockpit.
     */
    public function exactBirthdate(): ?Carbon
    {
        return $this->birthdate_estimated ? null : $this->birthdate;
    }

    /**
     * Stores an estimated birthdate in the middle of the given age year. The cockpit only works with ages,
     * the exact birthdate is reserved for the parents.
     */
    public function estimateBirthdateFromAge(int $years): static
    {
        $this->birthdate = now()->subYears($years)->subMonths(6)->startOfDay();
        $this->birthdate_estimated = true;

        return $this;
    }

    /**
     * Age in full years at the given day (default: today), or null without birthdate.
     */
    public function age(?Carbon $at = null): ?int
    {
        return $this->birthdate !== null
            ? (int) floor($this->birthdate->diffInYears($at ?? now()))
            : null;
    }
}
