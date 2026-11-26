<?php

namespace Database\Factories;

use App\Models\Annonce;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Annonces FICTIVES (tests).
 *
 * @extends Factory<Annonce>
 */
class AnnonceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titre' => fake()->sentence(4),
            'contenu' => '<p>'.fake()->sentence(12).'</p>',
            'position_image' => 'droite',
            'liens' => null,
            'visible' => true,
            'ordre' => 1,
        ];
    }
}
