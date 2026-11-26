<?php

namespace App\Models\Concerns;

use App\Services\Journal;

/**
 * Inscrit automatiquement les créations, modifications et suppressions du modèle
 * dans le journal des modifications.
 *
 * Avant de remplacer ou supprimer un fichier lié (photo, logo…), appeler archiverFichier()
 * pour que l'ancien fichier puisse être restauré.
 */
trait Journalise
{
    /** @var array<int, array{attribut: string, disque: string, chemin: string, archive: string}> */
    protected array $fichiersArchives = [];

    public static function bootJournalise(): void
    {
        static::created(fn (self $modele) => app(Journal::class)->apresCreation($modele));
        static::updated(fn (self $modele) => app(Journal::class)->apresModification($modele));
        static::deleted(fn (self $modele) => app(Journal::class)->apresSuppression($modele));
    }

    /**
     * Description lisible de l'élément dans le journal (ex. « Partenaire « COOP » »).
     */
    abstract public function libelleJournal(): string;

    /**
     * Noms français des champs, pour l'affichage du journal.
     *
     * @return array<string, string>
     */
    public static function libellesChampsJournal(): array
    {
        return [];
    }

    /**
     * Archive le fichier actuel avant qu'il soit remplacé ou supprimé.
     */
    public function archiverFichier(string $attribut, string $disque, string $chemin): void
    {
        $archive = app(Journal::class)->archiver($disque, $chemin);

        if ($archive !== null) {
            $this->fichiersArchives[] = ['attribut' => $attribut, 'disque' => $disque, 'chemin' => $chemin, 'archive' => $archive];
        }
    }

    /**
     * Inscrit au journal les fichiers archivés qui ne l'ont pas encore été
     * (cas d'un fichier remplacé sous le même nom, sans autre changement dans la base).
     */
    public function journaliserFichiersEnAttente(): void
    {
        if ($this->fichiersArchives !== []) {
            app(Journal::class)->apresModification($this);
        }
    }

    /**
     * @return array<int, array{attribut: string, disque: string, chemin: string, archive: string}>
     */
    public function prendreFichiersArchives(): array
    {
        $fichiers = $this->fichiersArchives;
        $this->fichiersArchives = [];

        return $fichiers;
    }
}
