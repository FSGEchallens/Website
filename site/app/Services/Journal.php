<?php

namespace App\Services;

use App\Models\JournalModification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Journal des modifications : enregistre qui a modifié quoi (valeurs avant / après, fichiers archivés)
 * et permet de restaurer une ancienne version ou un élément supprimé.
 *
 * Les modèles concernés utilisent le trait App\Models\Concerns\Journalise.
 */
class Journal
{
    private static ?string $note = null;

    /**
     * Exécute une action en ajoutant une note aux entrées du journal (ex. « Restauration de l'entrée n° 12 »).
     *
     * @template T
     *
     * @param  callable(): T  $action
     * @return T
     */
    public static function avecNote(string $note, callable $action): mixed
    {
        $precedente = self::$note;
        self::$note = $note;

        try {
            return $action();
        } finally {
            self::$note = $precedente;
        }
    }

    /**
     * Copie un fichier dans les archives du journal et renvoie le chemin de la copie (null s'il n'existe pas).
     */
    public function archiver(string $disque, string $chemin): ?string
    {
        if (! Storage::disk($disque)->exists($chemin)) {
            return null;
        }

        $archive = config('journal.dossier_archives').'/'.now()->format('Y-m').'/'.Str::lower(Str::random(12)).'-'.basename($chemin);

        Storage::disk('local')->put($archive, (string) Storage::disk($disque)->get($chemin));

        return $archive;
    }

    public function apresCreation(Model $modele): void
    {
        $this->ecrire($modele, JournalModification::CREATION, null, $this->valeursPourJournal($modele->getAttributes()), $modele->prendreFichiersArchives());
    }

    public function apresModification(Model $modele): void
    {
        $changements = Arr::except($modele->getChanges(), config('journal.champs_ignores'));
        $fichiers = $modele->prendreFichiersArchives();

        // Un fichier remplacé sous le même nom ne change pas la valeur du champ : on le signale quand même.
        foreach ($fichiers as $fichier) {
            $changements[$fichier['attribut']] ??= $modele->getAttributes()[$fichier['attribut']] ?? null;
        }

        if ($changements === []) {
            return;
        }

        $anciennes = [];
        foreach (array_keys($changements) as $champ) {
            $anciennes[$champ] = $modele->getRawOriginal($champ);
        }

        $this->ecrire(
            $modele,
            JournalModification::MODIFICATION,
            $this->valeursPourJournal($anciennes),
            $this->valeursPourJournal(Arr::only($modele->getAttributes(), array_keys($changements))),
            $fichiers,
        );
    }

    public function apresSuppression(Model $modele): void
    {
        $this->ecrire($modele, JournalModification::SUPPRESSION, $this->valeursPourJournal($modele->getAttributes(), garderTout: true), null, $modele->prendreFichiersArchives());
    }

    /**
     * Restaure l'état d'avant une modification, ou fait revenir un élément supprimé (fichiers compris).
     * La restauration est elle-même inscrite au journal.
     */
    public function restaurer(JournalModification $entree): Model
    {
        if (! $entree->estRestaurable()) {
            throw new RuntimeException('Cette entrée ne peut pas être restaurée.');
        }

        /** @var class-string<Model> $classe */
        $classe = $entree->sujet_type;
        $valeurs = Arr::except($entree->anciennes_valeurs ?? [], config('journal.champs_sans_valeur'));

        return self::avecNote('Restauration de l\'entrée n° '.$entree->id, fn () => DB::transaction(function () use ($entree, $classe, $valeurs) {
            if ($entree->action === JournalModification::SUPPRESSION) {
                if ($classe::query()->whereKey($entree->sujet_id)->exists()) {
                    throw new RuntimeException('Cet élément existe déjà : il a sans doute déjà été restauré.');
                }

                $modele = new $classe;
                $modele->setRawAttributes($valeurs);
                $this->remettreFichiers($modele, $entree);
                $modele->save();

                return $modele;
            }

            $modele = $classe::query()->find($entree->sujet_id);

            if ($modele === null) {
                throw new RuntimeException('Cet élément a été supprimé depuis. Restaurez d\'abord sa suppression.');
            }

            $modele->setRawAttributes(array_merge($modele->getAttributes(), $valeurs));
            $this->remettreFichiers($modele, $entree);
            $modele->save();

            if (! $modele->wasChanged() && $entree->fichiers) {
                $modele->touch();
            }

            $modele->journaliserFichiersEnAttente();

            return $modele;
        }));
    }

    /**
     * Remet les fichiers archivés à leur place. Le fichier actuel éventuel est archivé d'abord,
     * pour que la restauration puisse elle-même être annulée.
     */
    private function remettreFichiers(Model $modele, JournalModification $entree): void
    {
        foreach ($entree->fichiers ?? [] as $fichier) {
            if (! Storage::disk('local')->exists($fichier['archive'])) {
                throw new RuntimeException('Un fichier archivé est introuvable ('.basename($fichier['chemin']).').');
            }

            $modele->archiverFichier($fichier['attribut'], $fichier['disque'], $fichier['chemin']);
            Storage::disk($fichier['disque'])->put($fichier['chemin'], (string) Storage::disk('local')->get($fichier['archive']));
        }
    }

    /**
     * Retire les champs techniques et ne garde pas la valeur des champs sensibles (mot de passe).
     *
     * @param  array<string, mixed>  $valeurs
     * @return array<string, mixed>
     */
    private function valeursPourJournal(array $valeurs, bool $garderTout = false): array
    {
        if (! $garderTout) {
            $valeurs = Arr::except($valeurs, config('journal.champs_ignores'));
        }

        foreach (config('journal.champs_sans_valeur') as $champ) {
            if (array_key_exists($champ, $valeurs)) {
                $valeurs[$champ] = null;
            }
        }

        return $valeurs;
    }

    /**
     * @param  array<string, mixed>|null  $anciennes
     * @param  array<string, mixed>|null  $nouvelles
     * @param  array<int, array<string, string>>  $fichiers
     */
    private function ecrire(Model $modele, string $action, ?array $anciennes, ?array $nouvelles, array $fichiers): void
    {
        JournalModification::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'sujet_type' => $modele::class,
            'sujet_id' => $modele->getKey(),
            'libelle' => Str::limit($modele->libelleJournal(), 250),
            'anciennes_valeurs' => $anciennes,
            'nouvelles_valeurs' => $nouvelles,
            'fichiers' => $fichiers === [] ? null : $fichiers,
            'note' => self::$note,
            // Pas d'adresse IP pour les tâches automatiques (commandes lancées par le serveur).
            'ip' => app()->runningInConsole() && ! app()->runningUnitTests() ? null : request()->ip(),
        ]);
    }
}
