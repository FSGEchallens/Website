<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Crée (ou complète) le compte du webmaster avec le rôle Administrateur et lui envoie
 * le lien pour choisir son mot de passe. Utile pour démarrer sur un serveur vide.
 * Sur Plesk : tâche planifiée, script site/artisan, arguments « membre:admin e-mail Prénom Nom ».
 */
#[Signature('membre:admin {email : E-mail société} {prenom} {nom}')]
#[Description('Crée un administrateur et lui envoie un lien pour choisir son mot de passe')]
class CreerAdministrateur extends Command
{
    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => 'email'])->fails()) {
            $this->error("« {$email} » n'est pas une adresse e-mail valide.");

            return self::FAILURE;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'prenom' => (string) $this->argument('prenom'),
                'nom' => (string) $this->argument('nom'),
                'date_entree' => now()->toDateString(),
            ],
        );

        $user->forceFill(['role' => Role::Administrateur])->save();
        $user->envoyerInvitation();

        $this->info("Administrateur {$user->nomComplet()} prêt. Un lien pour choisir le mot de passe a été envoyé à {$email}.");

        return self::SUCCESS;
    }
}
