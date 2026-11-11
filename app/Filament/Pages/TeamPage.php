<?php

namespace App\Filament\Pages;

use App\Enums\TeamArea;
use App\Filament\Resources\Users\Schemas\TeamProfileFields;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;
use UnitEnum;

/**
 * Central management of the team shown on the homepage by the block "Team":
 * who appears in which area, in which order, with which photo and text.
 */
class TeamPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Homepage';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Team';

    protected static ?string $title = 'Team';

    protected static ?string $slug = 'team';

    #[Override]
    public static function canAccess(): bool
    {
        return parent::canAccess() && (bool) User::find(filament()->auth()->id())?->can('Homepage:Team');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => static::orderByTeamArea(User::query()->whereNotNull('team_area')))
            ->description('Reihenfolge per „Sortieren“ ändern – innerhalb eines Teambereichs. So erscheint das Team im Homepage-Block „Team“.')
            ->reorderable('team_sort')
            ->defaultSort('team_sort')
            ->paginated(false)
            ->defaultGroup(
                Group::make('team_area')
                    ->label('Teambereich')
                    ->getTitleFromRecordUsing(fn (User $record): string => $record->team_area?->getLabel() ?? '')
                    ->orderQueryUsing(fn (Builder $query): Builder => static::orderByTeamArea($query))
                    ->titlePrefixedWithLabel(false),
            )
            ->groupingSettingsHidden()
            ->columns([
                ImageColumn::make('team_photo_path')
                    ->label('Foto')
                    ->disk('public')
                    ->visibility('public')
                    ->circular(),
                TextColumn::make('name')
                    ->label('Name')
                    ->description(fn (User $record): ?string => $record->team_role)
                    ->searchable(),
                TextColumn::make('team_area')
                    ->label('Teambereich')
                    ->badge(),
                ToggleColumn::make('team_visible')
                    ->label('Auf der Homepage'),
            ])
            ->filters([
                SelectFilter::make('team_area')
                    ->label('Teambereich')
                    ->options(TeamArea::class),
            ])
            ->headerActions([
                Action::make('addMember')
                    ->label('Person hinzufügen')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->schema([
                        Select::make('user_id')
                            ->label('Benutzer')
                            ->options(fn (): array => User::query()->whereNull('team_area')->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                        TeamProfileFields::area(),
                    ])
                    ->modalDescription('Die Person erscheint erst auf der Homepage, wenn „Auf der Homepage“ eingeschaltet ist.')
                    ->action(function (array $data): void {
                        $member = User::query()->whereNull('team_area')->findOrFail($data['user_id']);
                        $member->update([
                            'team_area' => $data['team_area'],
                            'team_visible' => false,
                            'team_sort' => ((int) User::query()->whereNotNull('team_area')->max('team_sort')) + 1,
                        ]);

                        Notification::make()->success()->title($member->name.' ist jetzt im Team')->send();
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(TeamProfileFields::schema())
                    ->modalHeading(fn (User $record): string => 'Teamprofil: '.$record->name)
                    ->modalWidth('3xl'),
                Action::make('removeFromTeam')
                    ->label('Aus dem Team nehmen')
                    ->icon(Heroicon::OutlinedUserMinus)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Die Person verschwindet von der Homepage. Foto und Texte bleiben gespeichert, falls sie wieder hinzugefügt wird.')
                    ->action(fn (User $record) => $record->update(['team_area' => null, 'team_visible' => false])),
            ]);
    }

    /**
     * Sorts by the order of the team areas as defined in {@see TeamArea}, the same order as on the homepage.
     * The values are inlined instead of bound, because the reorder update reuses this query.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    protected static function orderByTeamArea(Builder $query): Builder
    {
        $cases = collect(TeamArea::cases())
            ->map(fn (TeamArea $area, int $position): string => "when '{$area->value}' then {$position}")
            ->implode(' ');

        return $query->orderByRaw("case team_area {$cases} end");
    }
}
