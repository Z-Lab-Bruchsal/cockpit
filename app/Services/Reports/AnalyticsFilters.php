<?php

namespace App\Services\Reports;

use App\Enums\CourseAudience;
use App\Models\Course;
use App\Models\Interest;
use App\Models\Merchant;
use App\Models\Orderstatus;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Validated state of the analytics filter form. The raw form state is not validated by Filament,
 * so every value is checked here; invalid dates fall back to the current year.
 */
final readonly class AnalyticsFilters
{
    /**
     * @param  list<int>  $interestIds
     * @param  list<int>  $courseIds
     * @param  list<int>  $merchantIds
     * @param  list<int>  $orderstatusIds
     * @param  list<int>  $userIds
     */
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $until,
        public array $interestIds = [],
        public array $courseIds = [],
        public ?CourseAudience $audience = null,
        public ?int $minAge = null,
        public ?int $maxAge = null,
        public array $merchantIds = [],
        public array $orderstatusIds = [],
        public array $userIds = [],
    ) {}

    /**
     * @param  array<string, mixed>|null  $state
     */
    public static function fromArray(?array $state): self
    {
        $state ??= [];
        $from = self::parseDay($state['from'] ?? null) ?? self::today()->startOfYear();
        $until = self::parseDay($state['to'] ?? null) ?? self::today()->endOfYear()->startOfDay();

        return new self(
            from: $from,
            until: $until->max($from),
            interestIds: self::ids($state['interests'] ?? []),
            courseIds: self::ids($state['courses'] ?? []),
            audience: CourseAudience::tryFrom((string) ($state['audience'] ?? '')),
            minAge: self::age($state['age_min'] ?? null),
            maxAge: self::age($state['age_max'] ?? null),
            merchantIds: self::ids($state['merchants'] ?? []),
            orderstatusIds: self::ids($state['orderstatus'] ?? []),
            userIds: self::ids($state['users'] ?? []),
        );
    }

    /**
     * Form defaults: the current year.
     *
     * @return array{from: string, to: string}
     */
    public static function defaults(): array
    {
        return [
            'from' => self::today()->startOfYear()->toDateString(),
            'to' => self::today()->endOfYear()->toDateString(),
        ];
    }

    /**
     * Start of the first day in UTC (inclusive).
     */
    public function start(): CarbonImmutable
    {
        return $this->from->startOfDay()->utc();
    }

    /**
     * Start of the day after the last day in UTC (exclusive).
     */
    public function end(): CarbonImmutable
    {
        return $this->until->addDay()->startOfDay()->utc();
    }

    public function hasAgeFilter(): bool
    {
        return $this->minAge !== null || $this->maxAge !== null;
    }

    public function acceptsAge(?int $age): bool
    {
        if (! $this->hasAgeFilter()) {
            return true;
        }

        if ($age === null) {
            return false;
        }

        return ($this->minAge === null || $age >= $this->minAge) && ($this->maxAge === null || $age <= $this->maxAge);
    }

    /**
     * One line describing the active filters, for printouts and the PDF.
     */
    public function describe(): string
    {
        $parts = ['Zeitraum: '.$this->from->format('d.m.Y').' – '.$this->until->format('d.m.Y')];

        if ($this->interestIds !== []) {
            $parts[] = 'Interessen: '.Interest::query()->whereKey($this->interestIds)->orderBy('name')->pluck('name')->join(', ');
        }

        if ($this->courseIds !== []) {
            $parts[] = 'Kurse: '.Course::query()->whereKey($this->courseIds)->orderBy('name')->pluck('name')->join(', ');
        }

        if ($this->audience !== null) {
            $parts[] = 'Zielgruppe: '.$this->audience->getLabel();
        }

        if ($this->hasAgeFilter()) {
            $parts[] = 'Alter: '.($this->minAge ?? '…').' – '.($this->maxAge ?? '…').' Jahre';
        }

        if ($this->merchantIds !== []) {
            $parts[] = 'Händler: '.Merchant::query()->whereKey($this->merchantIds)->orderBy('name')->pluck('name')->join(', ');
        }

        if ($this->orderstatusIds !== []) {
            $parts[] = 'Bestellstatus: '.Orderstatus::query()->whereKey($this->orderstatusIds)->orderBy('name')->pluck('name')->join(', ');
        }

        if ($this->userIds !== []) {
            $parts[] = 'Mitarbeiter: '.User::query()->whereKey($this->userIds)->orderBy('name')->pluck('name')->join(', ');
        }

        return implode(' · ', $parts);
    }

    private static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.business_timezone'))->startOfDay();
    }

    private static function parseDay(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}/', $value) !== 1) {
            return null;
        }

        return rescue(
            fn (): CarbonImmutable => CarbonImmutable::createFromFormat('Y-m-d', substr($value, 0, 10), config('app.business_timezone'))->startOfDay(),
            null,
            report: false,
        );
    }

    /**
     * @return list<int>
     */
    private static function ids(mixed $value): array
    {
        if (! is_array($value)) {
            $value = filled($value) ? [$value] : [];
        }

        return array_values(array_unique(array_map('intval', array_filter($value, fn (mixed $id): bool => is_numeric($id) && (int) $id > 0))));
    }

    private static function age(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value >= 0 && (int) $value <= 120 ? (int) $value : null;
    }
}
