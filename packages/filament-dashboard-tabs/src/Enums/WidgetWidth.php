<?php

namespace AlexanderGabriel\FilamentDashboardTabs\Enums;

use Filament\Support\Contracts\HasLabel;

enum WidgetWidth: string implements HasLabel
{
    case Half = 'half';
    case Full = 'full';

    public function getLabel(): string
    {
        return __("filament-dashboard-tabs::dashboard-tabs.widths.{$this->value}");
    }

    public function toggled(): self
    {
        return $this === self::Full ? self::Half : self::Full;
    }

    /**
     * Maps a Filament widget column span (in a two-column dashboard grid) to a width.
     *
     * @param  int|string|array<string, int|null>  $columnSpan
     */
    public static function fromColumnSpan(int|string|array $columnSpan): self
    {
        if (is_array($columnSpan)) {
            $columnSpan = $columnSpan['lg'] ?? $columnSpan['default'] ?? 1;
        }

        return ($columnSpan === 'full' || (int) $columnSpan >= 2) ? self::Full : self::Half;
    }
}
