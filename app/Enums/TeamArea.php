<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Areas the team is grouped by on the homepage, in display order.
 */
enum TeamArea: string implements HasLabel
{
    case Leitung = 'leitung';
    case Jugendleitung = 'jugendleitung';
    case Fachreferenten = 'fachreferenten';
    case Freiwilligendienst = 'bufdi';
    case Ehrenamt = 'ehrenamt';

    public function getLabel(): string
    {
        return match ($this) {
            self::Leitung => 'Leitung',
            self::Jugendleitung => 'Jugendleitung',
            self::Fachreferenten => 'Fachreferent*innen & Mentoring',
            self::Freiwilligendienst => 'Bundesfreiwilligendienst',
            self::Ehrenamt => 'Ehrenamt',
        };
    }
}
