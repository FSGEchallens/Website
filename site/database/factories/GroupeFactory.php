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
            'categorie' => null,
            'description' => '<p>'.fake()->sentence().'</p>',
            'age' => 'Dès 7 ans.',
            'prerequis' => 'Aucun prérequis !',
            'concours' => null,
            'prix' => 200,
            'infos_tarif' => "3 cours d'essai\nAssurance",
            'visible' => true,
        ];
    }
}
