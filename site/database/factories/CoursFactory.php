<?php

namespace Database\Factories;

use App\Models\Cours;
use App\Models\Groupe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cours>
 */
class CoursFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'groupe_id' => Groupe::factory(),
            'nom' => null,
            'jour' => 2,
            'heure_debut' => '18:00',
            'heure_fin' => '19:00',
            'salles' => [],
            'moniteurs' => [],
            'remarque' => null,
            'actif' => true,
        ];
    }
}
