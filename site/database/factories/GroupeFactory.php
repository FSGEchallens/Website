<?php

namespace Database\Factories;

use App\Models\Groupe;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Groupe>
 */
class GroupeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nom = 'Groupe '.fake()->unique()->word();

        return [
            'nom' => $nom,
            'slug' => Str::slug($nom),
            'categorie' => 'jeunesse',
            'description' => '<p>'.fake()->sentence().'</p>',
            'age' => '<p>Dès 7 ans.</p>',
            'prerequis' => '<p>Aucun prérequis !</p>',
            'concours' => null,
            'prix' => 200,
            'infos_tarif' => "<ul><li>3 cours d'essai</li><li>Assurance</li></ul>",
            'visible' => true,
        ];
    }
}
