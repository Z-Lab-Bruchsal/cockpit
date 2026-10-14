<?php

namespace App\Filament\Resources\CourseInstances;

use App\Filament\Resources\CourseInstances\Pages\CreateCourseInstance;
use App\Filament\Resources\CourseInstances\Pages\EditCourseInstance;
use App\Filament\Resources\CourseInstances\Pages\ListCourseInstances;
use App\Filament\Resources\CourseInstances\RelationManagers\AttendanceRelationManager;
use App\Filament\Resources\CourseInstances\RelationManagers\NotesRelationManager;
use App\Filament\Resources\CourseInstances\RelationManagers\ParticipantsRelationManager;
use App\Filament\Resources\CourseInstances\Schemas\CourseInstanceForm;
use App\Filament\Resources\CourseInstances\Tables\CourseInstancesTable;
use App\Models\CourseInstance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CourseInstanceResource extends Resource
{
    protected static ?string $model = CourseInstance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Kurse und Kinder';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Durchführungen';

    protected static ?string $modelLabel = 'Durchführung';

    protected static ?string $pluralModelLabel = 'Durchführungen';

    public static function form(Schema $schema): Schema
    {
        return CourseInstanceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CourseInstancesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ParticipantsRelationManager::class,
            AttendanceRelationManager::class,
            NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourseInstances::route('/'),
            'create' => CreateCourseInstance::route('/create'),
            'edit' => EditCourseInstance::route('/{record}/edit'),
        ];
    }
}
