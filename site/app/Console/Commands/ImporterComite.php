<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\MembresAncienSite;
use App\Support\NumeroMobile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Importe les membres du comité depuis la page « Le comité » de l'ancien site WordPress.
 *
 * Pour chaque fiche : le membre est retrouvé (même prénom et nom) ou créé, puis coché « Fait partie du comité »
 * avec sa fonction et l'e-mail de fonction (ex. presidence@gymechallens.ch), dans l'ordre de la page.
 * Son autorisation passe à Comité si elle était plus basse (un administrateur reste administrateur).
 * Aucune invitation n'est envoyée.
 *
 * Les données sont lues sur l'ancien site au moment de l'import, jamais mises dans le code (dépôt public).
 * À lancer sur le serveur. Sur Plesk : tâche planifiée, script site/artisan, arguments « comite:importer ».
 * Sans doublon : une personne déjà cochée « Fait partie du comité » est laissée telle quelle.
 */
#[Signature('comite:importer {--url=https://gymechallens.ch/le-comite/ : Page du comité de l\'ancien site}')]
#[Description('Importe les membres du comité depuis l\'ancien site WordPress')]
class ImporterComite extends Command
{
    public function handle(MembresAncienSite $membres): int
    {
        $url = (string) $this->option('url');

        try {
            $reponse = Http::timeout(30)->get($url);
        } catch (Throwable $exception) {
            $this->error('Impossible de lire '.$url.' : '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! $reponse->successful()) {
            $this->error('Impossible de lire '.$url.' (code '.$reponse->status().').');

            return self::FAILURE;
        }

        $fiches = $membres->fiches($reponse->body());

        if ($fiches === []) {
            $this->warn('Aucun membre du comité trouvé sur la page.');

            return self::SUCCESS;
        }

        $ordre = (int) User::where('comite_membre', true)->max('comite_ordre');

        foreach ($fiches as $nom => $fiche) {
            DB::transaction(function () use ($membres, $nom, $fiche, &$ordre): void {
                $membre = $membres->trouver($nom);
                $cree = $membre === null;

                // L'e-mail de la fiche est celui de la fonction : il ne devient pas l'identifiant de connexion.
                $membre ??= $membres->creer($nom, $fiche, Role::Comite, garderEmail: false);

                if ($membre->comite_membre) {
                    $this->line('Déjà au comité : '.$nom);

                    return;
                }

                $membre->comite_membre = true;
                $membre->comite_fonction = $fiche['poste'] ?: 'Membre du comité';
                $membre->comite_email = $this->emailDeFonction($fiche['email'] ?? null, $membre);
                $membre->mobile ??= NumeroMobile::normaliser($fiche['mobile'] ?? null);
                $membre->forceFill(['comite_ordre' => ++$ordre]);

                if (! $membre->roleReel()->auMoins(Role::Comite)) {
                    $membre->forceFill(['role' => Role::Comite]);
                }

                $membre->save();
                $this->info(($cree ? 'Créé et ajouté au comité : ' : 'Ajouté au comité : ').$nom.' ('.$membre->comite_fonction.')');
            });
        }

        $this->newLine();
        $this->info('Pensez à compléter les fiches créées (titre, e-mail société, e-mail privé), puis à envoyer les invitations.');

        return self::SUCCESS;
    }

    /**
     * E-mail de fonction du club (ex. presidence@…) ; ignoré s'il est déjà l'e-mail de connexion de la personne.
     */
    private function emailDeFonction(?string $email, User $membre): ?string
    {
        if ($email === null || ! str_ends_with($email, '@'.config('app.domaine_email')) || $email === $membre->email) {
            return null;
        }

        return $email;
    }
}
