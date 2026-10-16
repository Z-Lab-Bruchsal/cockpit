<?php

namespace App\Services\Recipients;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Users and groups selectable as recipients (of todos and messages) share one select.
 * Each option is identified by a key like "user:5" or "group:2".
 */
class Recipients
{
    public const string USER = 'user';

    public const string GROUP = 'group';

    /**
     * Options for the recipient select, grouped by type.
     *
     * @return array<string, array<string, string>>
     */
    public static function options(): array
    {
        return [
            'Personen' => User::query()->orderBy('name')->pluck('name', 'id')
                ->mapWithKeys(fn (string $name, int $id): array => [self::key(self::USER, $id) => $name])
                ->all(),
            'Gruppen' => Group::query()->orderBy('name')->get(['id', 'name', 'title'])
                ->mapWithKeys(fn (Group $group): array => [self::key(self::GROUP, $group->id) => $group->title ? "{$group->name} ({$group->title})" : $group->name])
                ->all(),
        ];
    }

    public static function key(string $type, int $id): string
    {
        return "{$type}:{$id}";
    }

    /**
     * @param  list<int>  $userIds
     * @param  list<int>  $groupIds
     * @return list<string>
     */
    public static function keys(array $userIds, array $groupIds): array
    {
        return [
            ...array_map(fn (int $id): string => self::key(self::USER, $id), $userIds),
            ...array_map(fn (int $id): string => self::key(self::GROUP, $id), $groupIds),
        ];
    }

    /**
     * Splits recipient keys into user and group ids; unknown keys are ignored.
     *
     * @param  array<int, string>|null  $keys
     * @return array{users: list<int>, groups: list<int>}
     */
    public static function parse(?array $keys): array
    {
        $parsed = ['users' => [], 'groups' => []];

        foreach ($keys ?? [] as $key) {
            [$type, $id] = array_pad(explode(':', (string) $key, 2), 2, null);

            if (! is_numeric($id)) {
                continue;
            }

            match ($type) {
                self::USER => $parsed['users'][] = (int) $id,
                self::GROUP => $parsed['groups'][] = (int) $id,
                default => null,
            };
        }

        return [
            'users' => array_values(array_unique($parsed['users'])),
            'groups' => array_values(array_unique($parsed['groups'])),
        ];
    }

    /**
     * All users reached by the given users and groups (group members included), each once.
     *
     * @param  list<int>  $userIds
     * @param  list<int>  $groupIds
     * @return Collection<int, User>
     */
    public static function resolveUsers(array $userIds, array $groupIds): Collection
    {
        return User::query()
            ->where(fn ($query) => $query
                ->whereIn('id', $userIds)
                ->orWhereHas('groups', fn ($groups) => $groups->whereIn('groups.id', $groupIds)))
            ->orderBy('name')
            ->get();
    }
}
