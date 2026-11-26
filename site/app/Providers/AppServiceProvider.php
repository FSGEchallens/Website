<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Carbon\Carbon;
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
        // Dates en français (« 14 septembre 2019 »).
        Carbon::setLocale(config('app.locale'));

        // Mot de passe fort : 12 à 64 caractères, majuscule, minuscule, chiffre et caractère spécial
        // (tous les signes et l'espace sont acceptés). Hors tests, refus des mots de passe ayant fuité
        // (Have I Been Pwned, anonyme : seuls 5 caractères de l'empreinte SHA-1 sont envoyés).
        Password::defaults(function () {
            $regle = Password::min(12)->max(64)->mixedCase()->numbers()->symbols();

            return $this->app->runningUnitTests() ? $regle : $regle->uncompromised();
        });

        // Voir et créer des membres : comité et administrateur.
        Gate::define('gerer-membres', fn (User $user) => $user->peutGererLesMembres());

        // Le comité ne peut pas modifier la fiche d'un administrateur (sinon il pourrait changer son e-mail de connexion).
        Gate::define('modifier-membre', fn (User $user, User $membre) => $user->estAdministrateur()
            || ($user->peutGererLesMembres() && ! $membre->estAdministrateur()));

        Gate::define('attribuer-role-administrateur', fn (User $user) => $user->estAdministrateur());

        // Contenus du site public (partenaires, puis pages…) : comité et administrateur.
        Gate::define('gerer-contenu', fn (User $user) => $user->aAuMoins(Role::Comite));

        // Résultats des concours : publiés par les moniteurs (et le comité, l'administrateur).
        Gate::define('publier-resultats', fn (User $user) => $user->aAuMoins(Role::Moniteur));

        // Journal des modifications (qui a modifié quoi, restauration) : administrateur uniquement pour le moment.
        Gate::define('consulter-journal', fn (User $user) => $user->estAdministrateur());
    }
}
