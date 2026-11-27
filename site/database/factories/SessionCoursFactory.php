<?php

namespace Database\Factories;

use App\Models\Cours;
use App\Models\SessionCours;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionCours>
 */
class SessionCoursFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cours_id' => Cours::factory(),
            'jour' => 4,
            'heure_debut' => '18:00',
            'heure_fin' => '20:00',
            'salles' => [],
            'moniteurs' => [],
            'remarque' => null,
        ];
    }
}
