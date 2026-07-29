<?php

namespace App\Filament\Resources\TimeProfiles;

use App\Filament\Resources\TimeProfiles\Pages\CreateTimeProfile;
use App\Filament\Resources\TimeProfiles\Pages\EditTimeProfile;
use App\Filament\Resources\TimeProfiles\Pages\ListTimeProfiles;
use App\Filament\Resources\TimeProfiles\Schemas\TimeProfileForm;
use App\Filament\Resources\TimeProfiles\Tables\TimeProfilesTable;
use App\Models\TimeProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TimeProfileResource extends Resource
{
    protected static ?string $model = TimeProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Zeiterfassung';

    protected static ?string $navigationLabel = 'Zeitmodelle';

    protected static ?string $pluralLabel = 'Zeitmodelle';

    protected static ?string $pluralModelLabel = 'Zeitmodelle';

    protected static ?string $modelLabel = 'Zeitmodell';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TimeProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TimeProfilesTable::configure($table);
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
            'index' => ListTimeProfiles::route('/'),
            'create' => CreateTimeProfile::route('/create'),
            'edit' => EditTimeProfile::route('/{record}/edit'),
        ];
    }
}
