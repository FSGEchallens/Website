<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        // Voir et créer des membres : comité et administrateur.
        Gate::define('gerer-membres', fn (User $user) => $user->peutGererLesMembres());

        // Le comité ne peut pas modifier la fiche d'un administrateur (sinon il pourrait changer son e-mail de connexion).
        Gate::define('modifier-membre', fn (User $user, User $membre) => $user->estAdministrateur()
            || ($user->peutGererLesMembres() && ! $membre->estAdministrateur()));

        Gate::define('attribuer-role-administrateur', fn (User $user) => $user->estAdministrateur());
    }
}
