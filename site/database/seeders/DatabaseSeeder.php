<?php

namespace Database\Seeders;

use App\Enums\Role;
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
        User::factory()->avecRole(Role::Administrateur)->create([
            'prenom' => 'Web',
            'nom' => 'Master',
            'email' => 'webmaster@'.config('app.domaine_email'),
        ]);
        User::factory(5)->avecRole(Role::Comite)->create();
        User::factory(8)->avecRole(Role::Moniteur)->create();
        User::factory(6)->create();
        User::factory(5)->sansCompte()->create();
    }
}
