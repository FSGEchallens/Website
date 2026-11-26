<?php

namespace Database\Factories;

use App\Enums\Civilite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Membres FICTIFS uniquement (développement et tests).
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $civilite = fake()->randomElement(Civilite::cases());
        $prenom = $civilite === Civilite::Madame ? fake()->firstNameFemale() : fake()->firstNameMale();
        $nom = fake()->lastName();
        $identifiant = Str::slug($prenom.'.'.$nom, '.');

        return [
            'civilite' => $civilite,
            'prenom' => $prenom,
            'nom' => $nom,
            'rue' => fake()->streetName(),
            'numero' => (string) fake()->numberBetween(1, 120),
            'npa' => (string) fake()->numberBetween(1000, 1999),
            'localite' => fake()->city(),
            'mobile' => '07'.fake()->randomElement(['6', '7', '8', '9']).' '.fake()->numerify('### ## ##'),
            'email_prive' => fake()->unique()->safeEmail(),
            'email' => $identifiant.'.'.fake()->unique()->numberBetween(1, 99999).'@example.org',
            'email_verified_at' => now(),
            'date_naissance' => fake()->dateTimeBetween('-70 years', '-16 years'),
            'date_entree' => fake()->dateTimeBetween('-20 years', 'now'),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Membre sans e-mail société (ne peut pas se connecter).
     */
    public function sansCompte(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
            'password' => null,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
