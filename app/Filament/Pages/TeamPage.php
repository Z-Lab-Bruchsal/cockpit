<?php

namespace App\Filament\Pages;

use App\Enums\TeamArea;
use App\Filament\Resources\Users\Schemas\TeamProfileFields;
use App\Models\TeamProfile;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
            ->query(fn (): Builder => static::orderByTeamArea(TeamProfile::query()->with('user')->whereNotNull('team_area')))
            ->description('Reihenfolge per „Sortieren“ ändern – innerhalb eines Teambereichs. So erscheint das Team im Homepage-Block „Team“.')
            ->reorderable('team_sort')
            ->defaultSort('team_sort')
            ->paginated(false)
            ->defaultGroup(
                Group::make('team_area')
                    ->label('Teambereich')
                    ->getTitleFromRecordUsing(fn (TeamProfile $record): string => $record->team_area?->getLabel() ?? '')
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
                    ->state(fn (TeamProfile $record): string => $record->displayName())
                    ->description(fn (TeamProfile $record): ?string => $record->team_role)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where(fn (Builder $query): Builder => $query
                            ->whereLike('name', '%'.$search.'%')
                            ->orWhereHas('user', fn (Builder $query): Builder => $query->whereLike('name', '%'.$search.'%')))),
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
                    ->label('Benutzer hinzufügen')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->schema([
                        Select::make('user_id')
                            ->label('Benutzer')
                            ->options(fn (): array => User::query()
                                ->whereDoesntHave('teamProfile', fn (Builder $query): Builder => $query->whereNotNull('team_area'))
                                ->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                        TeamProfileFields::area(),
                    ])
                    ->modalDescription('Die Person erscheint erst auf der Homepage, wenn „Auf der Homepage“ eingeschaltet ist.')
                    ->action(function (array $data): void {
                        $user = User::query()->findOrFail($data['user_id']);
                        $profile = $user->teamProfile()->firstOrNew();
                        abort_if($profile->team_area !== null, 409);

                        $profile->fill([
                            'team_area' => $data['team_area'],
                            'team_visible' => false,
                            'team_sort' => static::nextTeamSort(),
                        ])->save();

                        Notification::make()->success()->title($user->name.' ist jetzt im Team')->send();
                    }),
                Action::make('addStandaloneProfile')
                    ->label('Profil ohne Benutzerkonto hinzufügen')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->schema([
                        TextInput::make('name')->label('Name')->required()->maxLength(255),
                        TeamProfileFields::area(),
                    ])
                    ->action(function (array $data): void {
                        $profile = TeamProfile::query()->create([
                            'name' => $data['name'],
                            'team_area' => $data['team_area'],
                            'team_visible' => false,
                            'team_contactable' => false,
                            'team_sort' => static::nextTeamSort(),
                        ]);

                        Notification::make()->success()->title($profile->displayName().' ist jetzt im Team')->send();
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema([
                        TextInput::make('name')->label('Name')->required()->maxLength(255)
                            ->visible(fn (TeamProfile $record): bool => $record->user_id === null),
                        ...TeamProfileFields::schema(),
                    ])
                    ->modalHeading(fn (TeamProfile $record): string => 'Teamprofil: '.$record->displayName())
                    ->modalWidth('3xl'),
                Action::make('removeFromTeam')
                    ->label('Aus dem Team nehmen')
                    ->icon(Heroicon::OutlinedUserMinus)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Die Person verschwindet von der Homepage. Das Profil bleibt gespeichert.')
                    ->visible(fn (TeamProfile $record): bool => $record->user_id !== null)
                    ->action(function (TeamProfile $record): void {
                        abort_if($record->user_id === null, 403);

                        $record->update(['team_area' => null, 'team_visible' => false]);
                    }),
                Action::make('deleteStandaloneProfile')
                    ->label('Profil löschen')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (TeamProfile $record): bool => $record->user_id === null)
                    ->action(function (TeamProfile $record): void {
                        abort_unless($record->user_id === null, 403);

                        $record->delete();
                    }),
            ]);
    }

    protected static function nextTeamSort(): int
    {
        return (int) TeamProfile::query()->whereNotNull('team_area')->max('team_sort') + 1;
    }

    /**
     * Sorts by the order of the team areas as defined in {@see TeamArea}, the same order as on the homepage.
     * The values are inlined instead of bound, because the reorder update reuses this query.
     *
     * @param  Builder<TeamProfile>  $query
     * @return Builder<TeamProfile>
     */
    protected static function orderByTeamArea(Builder $query): Builder
    {
        $cases = collect(TeamArea::cases())
            ->map(fn (TeamArea $area, int $position): string => "when '{$area->value}' then {$position}")
            ->implode(' ');

        return $query->orderByRaw("case team_area {$cases} end");
    }
}
