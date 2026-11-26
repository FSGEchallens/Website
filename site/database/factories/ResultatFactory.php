<?php

namespace Database\Factories;

use App\Models\Resultat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Résultats FICTIFS (développement et tests).
 *
 * @extends Factory<Resultat>
 */
class ResultatFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('-3 years', 'now'),
            'date_fin' => null,
            'titre' => 'Concours '.fake()->city(),
            'contenu' => '<ul><li>C3 fille, '.fake()->lastName().' '.fake()->firstNameFemale().', '.fake()->numberBetween(1, 20).'e place</li></ul>',
            'photos' => null,
            'visible' => true,
        ];
    }
}
