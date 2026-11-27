<?php

namespace Database\Factories;

use App\Models\Batiment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batiment>
 */
class BatimentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => 'Bâtiment '.fake()->unique()->word(),
            'rue' => fake()->streetName().' '.fake()->buildingNumber(),
            'npa' => '1040',
            'localite' => 'Echallens',
            'lien_carte' => null,
            'informations' => null,
        ];
    }
}
