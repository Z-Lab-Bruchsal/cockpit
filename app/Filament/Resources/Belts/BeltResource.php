<?php

namespace App\Filament\Resources\Belts;

use App\Filament\Resources\Belts\Pages\CreateBelt;
use App\Filament\Resources\Belts\Pages\EditBelt;
use App\Filament\Resources\Belts\Pages\ListBelts;
use App\Filament\Resources\Belts\RelationManagers\CoursesRelationManager;
use App\Filament\Resources\Belts\Schemas\BeltForm;
use App\Filament\Resources\Belts\Tables\BeltsTable;
use App\Models\Belt;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BeltResource extends Resource
{
    protected static ?string $model = Belt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowUpCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Kurse und Kinder';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $pluralLabel = 'Gürtel';

    protected static ?string $label = 'Gürtel';

    protected static ?string $navigationLabel = 'Gürtel';

    protected static ?string $pluralModelLabel = 'Gürtel';

    protected static ?string $modelLabel = 'Gürtel';

    public static function form(Schema $schema): Schema
    {
        return BeltForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BeltsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CoursesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBelts::route('/'),
            'create' => CreateBelt::route('/create'),
            'edit' => EditBelt::route('/{record}/edit'),
        ];
    }
}
