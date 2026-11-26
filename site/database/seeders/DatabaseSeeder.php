<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Données FICTIVES pour le développement local uniquement.
     */
    public function run(): void
    {
        User::factory(20)->create();
        User::factory(5)->sansCompte()->create();
    }
}
