<?php

namespace Database\Factories;

use App\Models\Partenaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Partenaires FICTIFS (tests).
 *
 * @extends Factory<Partenaire>
 */
class PartenaireFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->company(),
            'url' => fake()->url(),
            'logo' => null,
            'ordre' => fake()->numberBetween(1, 100),
            'visible' => true,
        ];
    }

    public function masque(): static
    {
        return $this->state(fn (array $attributes) => ['visible' => false]);
    }
}
