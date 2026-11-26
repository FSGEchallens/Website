<?php

namespace Database\Factories;

use App\Models\Album;
use App\Models\PhotoGalerie;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Photos FICTIVES (tests) : aucune image réelle n'est créée sur le disque.
 *
 * @extends Factory<PhotoGalerie>
 */
class PhotoGalerieFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'album_id' => Album::factory(),
            'fichier' => 'photo-'.Str::lower(Str::random(10)).'.jpg',
            'nom_original' => 'IMG_'.fake()->numberBetween(1000, 9999).'.jpg',
            'largeur' => 1920,
            'hauteur' => 1280,
            'ordre' => fake()->numberBetween(1, 999),
        ];
    }
}
