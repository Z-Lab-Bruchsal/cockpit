<?php

namespace App\Models;

use Database\Factories\CourseDateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['course_instance_id', 'starts_at', 'ends_at', 'location'])]
class CourseDate extends Model
{
    /** @use HasFactory<CourseDateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function courseInstance(): BelongsTo
    {
        return $this->belongsTo(CourseInstance::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function effectiveLocation(): ?string
    {
        return $this->location ?: $this->courseInstance?->location;
    }

    /**
     * e.g. "Mi, 23.09.2026, 16:00–17:30 Uhr"
     */
    public function formattedRange(): string
    {
        $date = $this->starts_at->locale('de')->isoFormat('dd, DD.MM.YYYY');

        if ($this->starts_at->isSameDay($this->ends_at)) {
            return "{$date}, {$this->starts_at->format('H:i')}–{$this->ends_at->format('H:i')} Uhr";
        }

        return "{$date}, {$this->starts_at->format('H:i')} Uhr – {$this->ends_at->locale('de')->isoFormat('dd, DD.MM.YYYY')}, {$this->ends_at->format('H:i')} Uhr";
    }
}
