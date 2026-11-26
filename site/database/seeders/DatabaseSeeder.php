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
        User::factory()->avecRoles(Role::Administrateur)->create([
            'prenom' => 'Web',
            'nom' => 'Master',
            'email' => 'webmaster@example.org',
        ]);
        User::factory(3)->avecRoles(Role::Comite)->create();
        User::factory(2)->avecRoles(Role::Comite, Role::Moniteur)->create();
        User::factory(8)->avecRoles(Role::Moniteur)->create();
        User::factory(6)->create();
        User::factory(5)->sansCompte()->create();
    }
}
