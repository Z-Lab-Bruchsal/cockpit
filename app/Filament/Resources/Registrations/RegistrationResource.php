<?php

namespace App\Filament\Resources\Registrations;

use App\Enums\RegistrationStatus;
use App\Filament\Resources\Registrations\Pages\ListRegistrations;
use App\Filament\Resources\Registrations\Pages\ViewRegistration;
use App\Filament\Resources\Registrations\Schemas\RegistrationInfolist;
use App\Filament\Resources\Registrations\Tables\RegistrationsTable;
use App\Models\Registration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RegistrationResource extends Resource
{
    protected static ?string $model = Registration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Kurse und Kinder';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Anmeldungen';

    protected static ?string $modelLabel = 'Anmeldung';

    protected static ?string $pluralModelLabel = 'Anmeldungen';

    public static function getNavigationBadge(): ?string
    {
        $needsDecision = static::getModel()::query()
            ->where('status', RegistrationStatus::AwaitingApproval)
            ->orWhere(fn ($query) => $query->whereNotNull('approval_requested_at')->whereNull('approval_decided_at'))
            ->count();

        return $needsDecision > 0 ? (string) $needsDecision : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Freigabe nötig';
    }

    public static function infolist(Schema $schema): Schema
    {
        return RegistrationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RegistrationsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegistrations::route('/'),
            'view' => ViewRegistration::route('/{record}'),
        ];
    }
}
