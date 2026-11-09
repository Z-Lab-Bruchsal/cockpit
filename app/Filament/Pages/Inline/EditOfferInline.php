<?php

namespace App\Filament\Pages\Inline;

use App\Enums\CourseParticipation;
use App\Enums\CourseProminence;
use App\Filament\Resources\CourseInstances\Schemas\CourseInstanceForm;
use App\Filament\Resources\Courses\CourseResource;
use App\Models\CourseInstance;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Locked;

/**
 * Edits how one course instance appears on the homepage. Only the instance is changed, never the course,
 * so other instances of the same course stay as they are.
 */
class EditOfferInline extends InlineEditPage
{
    protected static ?string $slug = 'homepage/offer';

    protected static ?string $title = 'Angebot bearbeiten';

    #[Locked]
    public int $instanceId = 0;

    public function mount(): void
    {
        $this->instanceId = request()->integer('instance');

        $this->form->fill($this->instance()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        $instance = $this->instance();
        $courseUrl = CourseResource::getUrl('edit', ['record' => $instance->course_id]);

        return $schema
            ->components([
                Callout::make('Du bearbeitest nur die Durchführung „'.$instance->displayTitle().'“.')
                    ->description(new HtmlString(
                        'Kurs-Stammdaten und andere Durchführungen von „'.e($instance->course->name).'“ bleiben unverändert. Leere Felder zeigen weiter die Angaben vom Kurs. '
                        .'<a href="'.e($courseUrl).'" target="_top" class="font-semibold underline">Kurs im Cockpit öffnen</a>'
                    ))
                    ->warning()
                    ->columnSpanFull(),
                Hidden::make('course_id')
                    ->dehydrated(false),
                Hidden::make('participation')
                    ->dehydrated(false),
                TextInput::make('title')
                    ->label('Eigener Titel')
                    ->placeholder($instance->course->name)
                    ->maxLength(255),
                Select::make('prominence')
                    ->label('Darstellung in Angebotslisten')
                    ->options(CourseProminence::class)
                    ->selectablePlaceholder(false)
                    ->required(),
                TextInput::make('schedule_text')
                    ->label('Zeiten')
                    ->required()
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('participation') === CourseParticipation::DropIn->value),
                TextInput::make('schedule_note')
                    ->label('Hinweis')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('participation') === CourseParticipation::DropIn->value),
                ...CourseInstanceForm::displayFields(),
            ])
            ->columns(2)
            ->statePath('data');
    }

    protected function persist(array $data): void
    {
        $this->instance()->update($data);
    }

    protected function instance(): CourseInstance
    {
        $instance = CourseInstance::query()->with('course')->findOrFail($this->instanceId);

        Gate::authorize('update', $instance);

        return $instance;
    }
}
