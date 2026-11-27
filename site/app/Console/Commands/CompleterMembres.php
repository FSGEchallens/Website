<?php

namespace App\Console\Commands;

use App\Enums\Civilite;
use App\Services\MembresAncienSite;
use App\Support\LecteurTableur;
use App\Support\NumeroMobile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Complète les fiches des membres EXISTANTS à partir d'une liste du personnel (Excel .xlsx ou CSV) :
 * titre, rue, n°, NPA, localité, mobile et e-mail privé.
 *
 * - Les personnes de la liste qui n'ont pas de fiche ne sont PAS ajoutées (elles sont seulement listées).
 * - Un champ déjà rempli n'est pas écrasé, sauf avec --remplacer.
 * - Chaque modification est inscrite au journal.
 * - Le fichier contient des données personnelles : il n'est jamais mis dans Git. Il est déposé sur le serveur
 *   dans storage/app/private/import/ et supprimé après l'import (sauf avec --garder).
 *
 * Colonnes attendues : Titre, Prénom, Nom, Rue, N°, Code postal, Localité, Mobile, E-Mail (privé).
 * Sur Plesk : tâche planifiée, script site/artisan, arguments « membres:completer ».
 */
#[Signature('membres:completer {fichier=import/personnel.xlsx : Fichier dans storage/app/private} {--remplacer : Remplacer aussi les champs déjà remplis} {--garder : Ne pas supprimer le fichier après l\'import}')]
#[Description('Complète les fiches des membres existants à partir d\'une liste du personnel (Excel ou CSV)')]
class CompleterMembres extends Command
{
    /** En-têtes possibles (sans accents ni majuscules) => champ de la fiche. */
    private const COLONNES = [
        'titre' => 'civilite',
        'prenom' => 'prenom',
        'nom' => 'nom',
        'rue' => 'rue',
        'n°' => 'numero', 'no' => 'numero', 'numero' => 'numero',
        'code postal' => 'npa', 'npa' => 'npa',
        'localite' => 'localite',
        'mobile' => 'mobile', 'telephone' => 'mobile',
        'e-mail (prive)' => 'email_prive', 'email (prive)' => 'email_prive', 'e-mail prive' => 'email_prive', 'email prive' => 'email_prive',
    ];

    private const LIBELLES = [
        'civilite' => 'titre', 'rue' => 'rue', 'numero' => 'n°', 'npa' => 'NPA', 'localite' => 'localité',
        'mobile' => 'mobile', 'email_prive' => 'e-mail privé',
    ];

    public function handle(MembresAncienSite $membres): int
    {
        $disque = Storage::disk('local');
        $fichier = ltrim((string) $this->argument('fichier'), '/');

        if (! $disque->exists($fichier)) {
            $this->error('Fichier introuvable : storage/app/private/'.$fichier);

            return self::FAILURE;
        }

        try {
            $lignes = LecteurTableur::lire($disque->path($fichier));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $completes = 0;
        $absents = [];

        foreach ($lignes as $numero => $ligne) {
            $donnees = $this->normaliser($ligne);
            $nomComplet = trim(($donnees['prenom'] ?? '').' '.($donnees['nom'] ?? ''));

            if ($nomComplet === '') {
                continue;
            }

            $membre = $membres->trouver($nomComplet);

            if ($membre === null) {
                $absents[] = $nomComplet;

                continue;
            }

            $modifies = [];

            foreach ($this->valeurs($donnees, $nomComplet, $numero + 2) as $champ => $valeur) {
                $actuelle = $membre->getAttribute($champ);
                $actuelle = $actuelle instanceof Civilite ? $actuelle->value : $actuelle;

                if ($valeur === null || (string) $actuelle === (string) ($valeur instanceof Civilite ? $valeur->value : $valeur)) {
                    continue;
                }

                if ($actuelle !== null && $actuelle !== '' && ! $this->option('remplacer')) {
                    $this->line('  '.$nomComplet.' : '.self::LIBELLES[$champ].' déjà rempli, laissé tel quel (--remplacer pour le changer).');

                    continue;
                }

                $membre->setAttribute($champ, $valeur);
                $modifies[] = self::LIBELLES[$champ];
            }

            if ($modifies !== []) {
                $membre->save();
                $completes++;
                $this->info('Complété : '.$nomComplet.' ('.implode(', ', $modifies).')');
            }
        }

        $this->newLine();
        $this->info($completes.' fiche(s) complétée(s) sur '.count($lignes).' personne(s) dans la liste.');

        if ($absents !== []) {
            $this->warn('Sans fiche membre sur le site, non ajouté(e)s :');

            foreach ($absents as $nom) {
                $this->line(' - '.$nom);
            }
        }

        if (! $this->option('garder')) {
            $disque->delete($fichier);
            $this->line('Le fichier a été supprimé du serveur (données personnelles).');
        }

        return self::SUCCESS;
    }

    /**
     * Ligne du tableau → champs de la fiche (d'après les en-têtes).
     *
     * @param  array<string, string>  $ligne
     * @return array<string, string>
     */
    private function normaliser(array $ligne): array
    {
        $donnees = [];

        foreach ($ligne as $entete => $valeur) {
            $cle = Str::lower(Str::ascii(trim((string) preg_replace('/\s+/u', ' ', $entete))));
            $cle = str_replace('no.', 'no', $cle);
            $champ = self::COLONNES[$cle] ?? self::COLONNES[str_replace('n?', 'n°', $cle)] ?? (str_starts_with($cle, 'n') && mb_strlen($cle) <= 3 ? 'numero' : null);

            if ($champ !== null && trim($valeur) !== '') {
                $donnees[$champ] = trim($valeur);
            }
        }

        return $donnees;
    }

    /**
     * Valeurs vérifiées et mises au bon format (les valeurs incorrectes sont signalées et ignorées).
     *
     * @param  array<string, string>  $donnees
     * @return array<string, mixed>
     */
    private function valeurs(array $donnees, string $nomComplet, int $ligne): array
    {
        $valeurs = [
            'civilite' => isset($donnees['civilite']) ? Civilite::tryFrom(Str::lower(Str::ascii($donnees['civilite']))) : null,
            'rue' => isset($donnees['rue']) ? Str::limit($donnees['rue'], 150, '') : null,
            'numero' => isset($donnees['numero']) ? Str::limit($donnees['numero'], 10, '') : null,
            'npa' => isset($donnees['npa']) && preg_match('/^\d{4,5}$/', $donnees['npa']) ? $donnees['npa'] : null,
            'localite' => isset($donnees['localite']) ? Str::limit($donnees['localite'], 100, '') : null,
            'mobile' => isset($donnees['mobile']) ? NumeroMobile::normaliser($donnees['mobile']) : null,
            'email_prive' => isset($donnees['email_prive']) ? Str::lower($donnees['email_prive']) : null,
        ];

        foreach (['civilite' => 'titre', 'npa' => 'code postal', 'mobile' => 'mobile'] as $champ => $libelle) {
            if (isset($donnees[$champ]) && $valeurs[$champ] === null) {
                $this->warn('  Ligne '.$ligne.' ('.$nomComplet.') : '.$libelle.' non reconnu, ignoré.');
            }
        }

        if ($valeurs['email_prive'] !== null && Validator::make(['e' => $valeurs['email_prive']], ['e' => 'email'])->fails()) {
            $this->warn('  Ligne '.$ligne.' ('.$nomComplet.') : e-mail privé non valable, ignoré.');
            $valeurs['email_prive'] = null;
        }

        return $valeurs;
    }
}
