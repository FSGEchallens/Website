<?php

namespace Database\Factories;

use App\Models\Album;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Albums FICTIFS (tests).
 *
 * @extends Factory<Album>
 */
class AlbumFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titre = 'Spectacle '.fake()->unique()->numberBetween(2000, 2999);

        return [
            'titre' => $titre,
            'slug' => Str::slug($titre),
            'date' => fake()->dateTimeBetween('-5 years', 'now'),
            'description' => null,
            'visible' => true,
        ];
    }
}
