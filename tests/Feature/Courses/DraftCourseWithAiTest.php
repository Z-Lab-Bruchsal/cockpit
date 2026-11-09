<?php

namespace Tests\Feature\Courses;

use App\Ai\Agents\CourseDrafter;
use App\Enums\CourseAudience;
use App\Filament\Actions\DraftCourseWithAiAction;
use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Models\Interest;
use App\Models\User;
use App\Services\Ai\LocalModels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Prompts\AgentPrompt;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DraftCourseWithAiTest extends TestCase
{
    use RefreshDatabase;

    protected Interest $technology;

    protected Interest $robotics;

    protected bool $modelsEndpointFails = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        config([
            'ai.providers.local.url' => 'http://llm.test/v1',
            'ai.providers.local.key' => 'secret',
            'ai.local_models.draft' => 'qwen3.5-35b-a3b',
        ]);

        Http::preventStrayRequests();
        Http::fake(['llm.test/*' => fn () => $this->modelsEndpointFails
            ? Http::response(status: 502)
            : Http::response(['data' => [
                ['id' => 'qwen3.5-35b-a3b', 'name' => 'Qwen3.5 35B-A3B', 'status' => ['value' => 'loaded']],
                ['id' => 'qwen3.5-9b', 'name' => 'Qwen3.5 9B', 'status' => ['value' => 'unloaded']],
            ]])]);

        $this->technology = Interest::factory()->create(['name' => 'Technik', 'is_active' => true]);
        $this->robotics = Interest::factory()->create(['name' => 'Robotik', 'is_active' => true]);

        $user = User::factory()->create();

        foreach (['ViewAny', 'View', 'Create', 'Update'] as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$ability}:Course"));
        }

        $this->actingAs($user);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function modelOutput(array $overrides = []): array
    {
        return [
            'name' => 'Fischertechnik-Werkstatt',
            'teaser' => 'Baue Maschinen mit Motoren und Zahnrädern.',
            'description' => '<h2>Worum geht es?</h2><p>Wir bauen.</p>',
            'duration' => 120,
            'min_age' => 9,
            'max_age' => 11,
            'default_capacity' => 10,
            'interests' => ['Technik', 'robotik'],
            ...$overrides,
        ];
    }

    public function test_action_fills_the_form_with_the_draft(): void
    {
        CourseDrafter::fake([$this->modelOutput()])->preventStrayPrompts();

        Livewire::test(CreateCourse::class)
            ->callAction(DraftCourseWithAiAction::class, [
                'wish' => '2h-Kurs Fischertechnik für 10-Jährige',
                'model' => 'qwen3.5-9b',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Entwurf eingefügt')
            ->assertSchemaStateSet([
                'name' => 'Fischertechnik-Werkstatt',
                'teaser' => 'Baue Maschinen mit Motoren und Zahnrädern.',
                'description' => '<h2>Worum geht es?</h2><p>Wir bauen.</p>',
                'duration' => 120,
                'min_age' => 9,
                'max_age' => 11,
                'default_capacity' => 10,
                'audience' => CourseAudience::Kids,
            ]);

        CourseDrafter::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Fischertechnik für 10-Jährige')
            && $prompt->model === 'qwen3.5-9b');
    }

    public function test_draft_maps_interest_names_to_ids_and_ignores_unknown_ones(): void
    {
        $data = (new CourseDrafter)->toFormData($this->modelOutput(['interests' => ['Technik', 'ROBOTIK', 'Kochen', 42]]));

        $this->assertEqualsCanonicalizing([$this->technology->id, $this->robotics->id], $data['interests']);
    }

    public function test_draft_sanitizes_html_numbers_and_teaser(): void
    {
        $data = (new CourseDrafter)->toFormData($this->modelOutput([
            'name' => '<think>hmm</think> <b>Robo</b>  Kurs',
            'teaser' => str_repeat('a', 400),
            'description' => '<think>überlegen</think><h2 class="x" onclick="evil()">Titel</h2><script>alert(1)</script><p>Text <a href="https://x.test">Link</a></p>',
            'duration' => 0,
            'min_age' => 14,
            'max_age' => 8,
            'default_capacity' => 'viele',
        ]));

        $this->assertSame('Robo Kurs', $data['name']);
        $this->assertSame(CourseDrafter::TEASER_MAX_LENGTH, mb_strlen($data['teaser']));
        $this->assertSame('<h2>Titel</h2>alert(1)<p>Text Link</p>', $data['description']);
        $this->assertSame(1, $data['duration']);
        $this->assertSame(8, $data['min_age']);
        $this->assertSame(14, $data['max_age']);
        $this->assertNull($data['default_capacity']);
    }

    public function test_provider_error_leaves_the_form_untouched(): void
    {
        CourseDrafter::fake(function (): never {
            throw new ProviderConnectionException('Connection refused');
        });

        Livewire::test(CreateCourse::class)
            ->fillForm(['name' => 'Mein Kurs'])
            ->callAction(DraftCourseWithAiAction::class, [
                'wish' => 'Lötkurs',
                'model' => 'qwen3.5-35b-a3b',
            ])
            ->assertNotified('Kein Entwurf möglich')
            ->assertSchemaStateSet(['name' => 'Mein Kurs', 'teaser' => null]);
    }

    public function test_wish_is_required(): void
    {
        CourseDrafter::fake()->preventStrayPrompts();

        Livewire::test(CreateCourse::class)
            ->callAction(DraftCourseWithAiAction::class, ['wish' => '', 'model' => 'qwen3.5-35b-a3b'])
            ->assertHasActionErrors(['wish' => 'required']);

        CourseDrafter::assertNeverPrompted();
    }

    public function test_action_is_hidden_without_configured_endpoint(): void
    {
        config(['ai.providers.local.url' => null]);

        Livewire::test(CreateCourse::class)
            ->assertActionHidden(DraftCourseWithAiAction::class);
    }

    public function test_thinking_is_disabled_for_the_local_provider_only(): void
    {
        $drafter = new CourseDrafter;

        $this->assertSame(['chat_template_kwargs' => ['enable_thinking' => false]], $drafter->providerOptions('local'));
        $this->assertSame([], $drafter->providerOptions('openai'));
    }

    public function test_model_options_come_from_the_endpoint(): void
    {
        $this->assertSame([
            'qwen3.5-35b-a3b' => 'Qwen3.5 35B-A3B (geladen)',
            'qwen3.5-9b' => 'Qwen3.5 9B',
        ], LocalModels::options(LocalModels::ROLE_DRAFT));

        Http::assertSent(fn ($request): bool => $request->url() === 'http://llm.test/v1/models'
            && $request->hasHeader('Authorization', 'Bearer secret'));
    }

    public function test_model_options_fall_back_to_the_default_when_endpoint_fails(): void
    {
        $this->modelsEndpointFails = true;

        $this->assertSame(['qwen3.5-35b-a3b' => 'qwen3.5-35b-a3b'], LocalModels::options(LocalModels::ROLE_DRAFT));
    }
}
