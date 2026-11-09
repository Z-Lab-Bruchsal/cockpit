<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\ColorPicker;

/**
 * Color picker that only accepts #rgb or #rrggbb, so values can safely be used in CSS.
 */
class HexColorPicker extends ColorPicker
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->hex()
            ->regex('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/');
    }
}
