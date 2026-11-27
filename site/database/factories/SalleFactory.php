<?php

namespace Database\Factories;

use App\Models\Batiment;
use App\Models\Salle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Salle>
 */
class SalleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batiment_id' => Batiment::factory(),
            'nom' => 'Salle '.fake()->unique()->numberBetween(1, 9999),
        ];
    }
}
