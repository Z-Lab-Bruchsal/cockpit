<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Orderstatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->words(3, true)),
            'url' => 'https://www.reichelt.de/artikel-'.fake()->numberBetween(1000, 9999).'.html',
            'count' => fake()->numberBetween(1, 5),
            'orderstatus_id' => fn (): int => Orderstatus::query()->firstOrCreate(['name' => 'erfasst'])->id,
            'user_id' => User::factory(),
        ];
    }

    /**
     * An order that was placed, with merchant data.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function ordered(array $attributes = []): static
    {
        return $this->state(fn (array $state): array => [
            'orderstatus_id' => Orderstatus::query()->firstOrCreate(['name' => 'bestellt'])->id,
            'orderdatetime' => now(),
            'order_number' => 'B-'.fake()->numberBetween(10000, 99999),
            'total_price' => fake()->randomFloat(2, 1, 200),
            ...$attributes,
        ]);
    }
}
