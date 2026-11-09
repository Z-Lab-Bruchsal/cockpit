<?php

namespace Database\Factories;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Merchant>
 */
class MerchantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
        ];
    }

    public function withDomain(string $domain): static
    {
        return $this->afterCreating(fn (Merchant $merchant) => $merchant->domains()->create(['domain' => $domain]));
    }
}
