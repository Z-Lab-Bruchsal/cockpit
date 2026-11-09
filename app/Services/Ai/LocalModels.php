<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Models offered by the local OpenAI-compatible endpoint (e.g. llama-swap), which loads the requested model on demand.
 */
class LocalModels
{
    public const PROVIDER = 'local';

    public const ROLE_DRAFT = 'draft';

    public const ROLE_FAST = 'fast';

    public const ROLE_TOOLS = 'tools';

    /**
     * Whether a local endpoint is configured at all.
     */
    public static function isConfigured(): bool
    {
        return filled(config('ai.providers.'.self::PROVIDER.'.url'));
    }

    /**
     * The configured model id for a role.
     */
    public static function forRole(string $role): string
    {
        return (string) config("ai.local_models.{$role}");
    }

    /**
     * Models the endpoint currently serves, keyed by id; loaded models are marked as such.
     *
     * @return array<string, string>
     */
    public static function available(): array
    {
        if (! static::isConfigured()) {
            return [];
        }

        return Cache::remember('ai.local_models.available', 60, function (): array {
            try {
                $response = Http::withToken((string) config('ai.providers.'.self::PROVIDER.'.key'))
                    ->timeout(5)
                    ->get(rtrim((string) config('ai.providers.'.self::PROVIDER.'.url'), '/').'/models')
                    ->throw();
            } catch (Throwable $exception) {
                Log::warning('Lokale KI-Modelle konnten nicht abgefragt werden.', ['error' => $exception->getMessage()]);

                return [];
            }

            return collect($response->json('data', []))
                ->filter(fn (mixed $model): bool => is_array($model) && filled($model['id'] ?? null))
                ->mapWithKeys(function (array $model): array {
                    $label = $model['name'] ?? $model['id'];

                    if (($model['status']['value'] ?? null) === 'loaded') {
                        $label .= ' (geladen)';
                    }

                    return [$model['id'] => $label];
                })
                ->all();
        });
    }

    /**
     * Select options for choosing a model, always containing the role's default model.
     *
     * @return array<string, string>
     */
    public static function options(string $role): array
    {
        $default = static::forRole($role);

        return static::available() ?: [$default => $default];
    }
}
