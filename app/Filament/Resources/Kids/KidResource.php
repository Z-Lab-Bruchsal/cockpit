<?php

namespace App\Filament\Resources\Kids;

use App\Filament\Resources\Kids\Pages\CreateKid;
use App\Filament\Resources\Kids\Pages\EditKid;
use App\Filament\Resources\Kids\Pages\ListKids;
use App\Filament\Resources\Kids\RelationManagers\CoursesRelationManager;
use App\Filament\Resources\Kids\Schemas\KidForm;
use App\Filament\Resources\Kids\Schemas\KidInfolist;
use App\Filament\Resources\Kids\Tables\KidsTable;
use App\Models\Kid;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class KidResource extends Resource
{
    protected static ?string $model = Kid::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Kurse und Kinder';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $pluralLabel = 'Kinder';

    protected static ?string $label = 'Kind';

    protected static ?string $navigationLabel = 'Kinder';

    protected static ?string $pluralModelLabel = 'Kinder';

    protected static ?string $modelLabel = 'Kind';

    public static function form(Schema $schema): Schema
    {
        return KidForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return KidInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KidsTable::configure($table);
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
            'index' => ListKids::route('/'),
            'create' => CreateKid::route('/create'),
            'edit' => EditKid::route('/{record}/edit'),
        ];
    }
}
