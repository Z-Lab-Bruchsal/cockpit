<?php

namespace App\Filament\Resources\TerminalEvents;

use App\Filament\Resources\TerminalEvents\Pages\ListTerminalEvents;
use App\Filament\Resources\TerminalEvents\Tables\TerminalEventsTable;
use App\Models\TerminalEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-only log of every chip presented at a terminal.
 */
class TerminalEventResource extends Resource
{
    protected static ?string $model = TerminalEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = 'Zeiterfassung';

    protected static ?string $navigationLabel = 'Stempel-Protokoll';

    protected static ?string $pluralModelLabel = 'Stempel-Protokoll';

    protected static ?string $modelLabel = 'Stempel-Ereignis';

    public static function table(Table $table): Table
    {
        return TerminalEventsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTerminalEvents::route('/'),
        ];
    }
}
