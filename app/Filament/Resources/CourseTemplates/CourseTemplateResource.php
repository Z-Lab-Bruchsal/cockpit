<?php

namespace App\Filament\Resources\CourseTemplates;

use App\Filament\Resources\CourseTemplates\Pages\CreateCourseTemplate;
use App\Filament\Resources\CourseTemplates\Pages\EditCourseTemplate;
use App\Filament\Resources\CourseTemplates\Pages\ListCourseTemplates;
use App\Filament\Resources\CourseTemplates\Schemas\CourseTemplateForm;
use App\Filament\Resources\CourseTemplates\Tables\CourseTemplatesTable;
use App\Models\CourseTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CourseTemplateResource extends Resource
{
    protected static ?string $model = CourseTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = 'Kurse und Kinder';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Darstellungsvorlagen';

    protected static ?string $modelLabel = 'Darstellungsvorlage';

    protected static ?string $pluralModelLabel = 'Darstellungsvorlagen';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CourseTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CourseTemplatesTable::configure($table);
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
            'index' => ListCourseTemplates::route('/'),
            'create' => CreateCourseTemplate::route('/create'),
            'edit' => EditCourseTemplate::route('/{record}/edit'),
        ];
    }
}
