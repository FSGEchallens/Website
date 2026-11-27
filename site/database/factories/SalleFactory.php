<?php

namespace Database\Factories;

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
            'nom' => 'Salle '.fake()->unique()->word(),
            'rue' => fake()->streetName().' '.fake()->buildingNumber(),
            'npa' => '1040',
            'localite' => 'Echallens',
            'lien_carte' => null,
            'informations' => null,
        ];
    }
}
