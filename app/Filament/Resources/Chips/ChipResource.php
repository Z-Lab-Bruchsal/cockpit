<?php

namespace App\Filament\Resources\Chips;

use App\Filament\Resources\Chips\Pages\CreateChip;
use App\Filament\Resources\Chips\Pages\EditChip;
use App\Filament\Resources\Chips\Pages\ListChips;
use App\Filament\Resources\Chips\Schemas\ChipForm;
use App\Filament\Resources\Chips\Tables\ChipsTable;
use App\Models\Chip;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ChipResource extends Resource
{
    protected static ?string $model = Chip::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Zeiterfassung';

    protected static ?string $navigationLabel = 'Chips';

    protected static ?string $pluralModelLabel = 'Chips';

    protected static ?string $modelLabel = 'Chip';

    protected static ?string $recordTitleAttribute = 'uid';

    public static function form(Schema $schema): Schema
    {
        return ChipForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChipsTable::configure($table);
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
            'index' => ListChips::route('/'),
            'create' => CreateChip::route('/create'),
            'edit' => EditChip::route('/{record}/edit'),
        ];
    }
}
