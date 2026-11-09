<?php

namespace App\Ai\Agents;

use App\Models\Interest;
use App\Services\Ai\LocalModels;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Drafts a course (texts and settings) from a short wish like "2h-Kurs Fischertechnik für 10-Jährige".
 */
#[MaxTokens(4096)]
class CourseDrafter implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public const ALLOWED_TAGS = ['h2', 'h3', 'p', 'ul', 'ol', 'li', 'strong', 'em', 'br'];

    public const TEASER_MAX_LENGTH = 300;

    /**
     * @var Collection<int, Interest>|null
     */
    protected ?Collection $interests = null;

    public static function isAvailable(): bool
    {
        return LocalModels::isConfigured();
    }

    public function instructions(): Stringable|string
    {
        $interests = $this->interests()->pluck('name')->implode(', ') ?: '(keine)';
        $tags = implode(', ', self::ALLOWED_TAGS);

        return <<<PROMPT
            Du bist Kursplaner im z-lab, einem außerschulischen MINT-Lernort für Kinder und Jugendliche.
            Aus einem kurzen Wunsch erstellst du einen vollständigen Kursentwurf auf Deutsch.

            Zielgruppe der Texte sind Eltern und Kinder: freundlich, konkret, motivierend, ohne Werbefloskeln. Sprich die Kinder mit "du" an.

            Felder:
            - name: kurzer, griffiger Kursname.
            - teaser: ein bis zwei Sätze für die Kurs-Visitenkarte, höchstens 300 Zeichen.
            - description: ausführliche Kursbeschreibung als HTML. Erlaubte Tags: {$tags}. Keine Attribute, kein Markdown.
              Gliedere mit <h2>/<h3>: Worum geht es?, Ablauf (Zeitblöcke passend zur Dauer, inkl. kurzer Pause bei mehr als 90 Minuten), Was du lernst (Lernziele), Material, Für wen?
            - duration: Dauer eines Termins in Minuten.
            - min_age, max_age: sinnvolle Altersspanne in Jahren.
            - default_capacity: sinnvolle Teilnehmerzahl für das Format (meist 8 bis 12).
            - interests: passende Themen, ausschließlich aus dieser Liste: {$interests}.

            Übernimm Angaben aus dem Wunsch (Dauer, Alter, Thema, Material) genau. Erfinde keine Preise, Termine oder Orte und nenne keine Marken oder Produkte, die nicht im Wunsch stehen.
            PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'teaser' => $schema->string()->required(),
            'description' => $schema->string()->required(),
            'duration' => $schema->integer()->min(1)->required(),
            'min_age' => $schema->integer()->min(0)->max(99)->required(),
            'max_age' => $schema->integer()->min(0)->max(99)->required(),
            'default_capacity' => $schema->integer()->min(1)->required(),
            'interests' => $schema->array()->items($schema->string())->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        if ($provider !== LocalModels::PROVIDER) {
            return [];
        }

        return [
            'chat_template_kwargs' => ['enable_thinking' => (bool) config('ai.local_thinking')],
        ];
    }

    /**
     * Ask the model for a course draft and map it onto the course form fields.
     *
     * @return array{name: string, teaser: string, description: string, duration: int|null, min_age: int|null, max_age: int|null, default_capacity: int|null, interests: list<int>}
     */
    public function draft(string $wish, ?string $model = null): array
    {
        $response = $this->prompt(
            $wish,
            provider: LocalModels::PROVIDER,
            model: $model ?: LocalModels::forRole(LocalModels::ROLE_DRAFT),
            timeout: (int) config('ai.local_timeout'),
        );

        return $this->toFormData($response->toArray());
    }

    /**
     * Sanitize raw model output into values the course form accepts.
     *
     * @param  array<string, mixed>  $output
     * @return array{name: string, teaser: string, description: string, duration: int|null, min_age: int|null, max_age: int|null, default_capacity: int|null, interests: list<int>}
     */
    public function toFormData(array $output): array
    {
        $minAge = $this->clampedInteger($output['min_age'] ?? null, 0, 99);
        $maxAge = $this->clampedInteger($output['max_age'] ?? null, 0, 99);

        if ($minAge !== null && $maxAge !== null && $maxAge < $minAge) {
            [$minAge, $maxAge] = [$maxAge, $minAge];
        }

        return [
            'name' => Str::limit($this->plainText($output['name'] ?? ''), 255, ''),
            'teaser' => mb_substr($this->plainText($output['teaser'] ?? ''), 0, self::TEASER_MAX_LENGTH),
            'description' => $this->sanitizeHtml((string) ($output['description'] ?? '')),
            'duration' => $this->clampedInteger($output['duration'] ?? null, 1, 24 * 60),
            'min_age' => $minAge,
            'max_age' => $maxAge,
            'default_capacity' => $this->clampedInteger($output['default_capacity'] ?? null, 1, 500),
            'interests' => $this->interestIds((array) ($output['interests'] ?? [])),
        ];
    }

    /**
     * @return Collection<int, Interest>
     */
    protected function interests(): Collection
    {
        return $this->interests ??= Interest::query()->active()->get(['id', 'name']);
    }

    /**
     * @param  array<int, mixed>  $names
     * @return list<int>
     */
    protected function interestIds(array $names): array
    {
        $wanted = collect($names)
            ->filter(fn (mixed $name): bool => is_string($name))
            ->map(fn (string $name): string => Str::lower(trim($name)));

        return $this->interests()
            ->filter(fn (Interest $interest): bool => $wanted->contains(Str::lower($interest->name)))
            ->pluck('id')
            ->values()
            ->all();
    }

    protected function clampedInteger(mixed $value, int $min, int $max): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max($min, min($max, (int) $value));
    }

    protected function plainText(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags($this->withoutThinking((string) $value))));
    }

    /**
     * Keep only the allowed tags and drop all their attributes.
     */
    protected function sanitizeHtml(string $html): string
    {
        $tags = implode('|', self::ALLOWED_TAGS);
        $html = strip_tags($this->withoutThinking($html), self::ALLOWED_TAGS);

        return trim(preg_replace("/<(\/?)({$tags})\b[^>]*>/i", '<$1$2>', $html));
    }

    /**
     * Remove reasoning blocks some local models leak into their answer.
     */
    protected function withoutThinking(string $text): string
    {
        return preg_replace('/<think>.*?<\/think>/is', '', $text);
    }
}
