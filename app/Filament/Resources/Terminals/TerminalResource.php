<?php

namespace App\Filament\Resources\Terminals;

use App\Filament\Resources\Terminals\Pages\CreateTerminal;
use App\Filament\Resources\Terminals\Pages\EditTerminal;
use App\Filament\Resources\Terminals\Pages\ListTerminals;
use App\Filament\Resources\Terminals\Schemas\TerminalForm;
use App\Filament\Resources\Terminals\Tables\TerminalsTable;
use App\Models\Terminal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TerminalResource extends Resource
{
    protected static ?string $model = Terminal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDeviceTablet;

    protected static string|UnitEnum|null $navigationGroup = 'Zeiterfassung';

    protected static ?string $navigationLabel = 'Terminals';

    protected static ?string $pluralModelLabel = 'Terminals';

    protected static ?string $modelLabel = 'Terminal';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TerminalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TerminalsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTerminals::route('/'),
            'create' => CreateTerminal::route('/create'),
            'edit' => EditTerminal::route('/{record}/edit'),
        ];
    }
}
