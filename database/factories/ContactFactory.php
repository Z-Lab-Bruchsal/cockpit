<?php

namespace Database\Factories;

use App\Enums\ContactType;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ContactType::Parent,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('07251 ######'),
            'email_verified_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => ['email_verified_at' => null]);
    }

    public function withInterestMailsConsent(): static
    {
        return $this->state(fn (array $attributes): array => ['interest_mails_consent_at' => now()]);
    }

    public function school(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ContactType::School,
            'name' => fake()->city().' Grundschule',
            'contact_person' => fake()->name(),
            'street' => fake()->streetAddress(),
            'zip' => fake()->postcode(),
            'city' => fake()->city(),
        ]);
    }

    /**
     * Teacher sub-account of the given school, or of a new one.
     */
    public function teacher(?Contact $school = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ContactType::Teacher,
            'school_id' => $school?->id ?? Contact::factory()->school(),
        ]);
    }
}
