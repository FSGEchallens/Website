<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Database\Factories\ResultatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Résultat d'un concours ou d'un événement (page publique « Résultats »).
 *
 * @property array<int, string>|null $photos
 */
#[Fillable(['date', 'date_fin', 'titre', 'contenu', 'visible'])]
class Resultat extends Model
{
    /** @use HasFactory<ResultatFactory> */
    use HasFactory, Journalise;

    public const PHOTOS_MAX = 10;

    public const DISQUE = 'local';

    public const DOSSIER_PHOTOS = 'resultats/photos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'date_fin' => 'date',
            'photos' => 'array',
            'visible' => 'boolean',
        ];
    }

    /**
     * Du plus récent au plus ancien.
     *
     * @param  Builder<Resultat>  $query
     */
    public function scopeDuPlusRecent(Builder $query): void
    {
        $query->orderByDesc('date')->orderByDesc('id');
    }

    /**
     * À l'intérieur du modèle, $this->visible est une propriété interne de Laravel : on lit la colonne.
     */
    public function estAffichable(): bool
    {
        return (bool) $this->getAttribute('visible');
    }

    /**
     * Date lisible : « 2 avril 2022 », « 14-15 septembre 2019 », « 30 mai - 2 juin 2019 »…
     */
    public function dateLisible(): string
    {
        $debut = $this->date;
        $fin = $this->date_fin;

        if ($debut === null) {
            return '';
        }

        if ($fin === null || $fin->isSameDay($debut)) {
            return $debut->translatedFormat('j F Y');
        }

        if ($fin->isSameMonth($debut)) {
            return $debut->format('j').'-'.$fin->translatedFormat('j F Y');
        }

        if ($fin->isSameYear($debut)) {
            return $debut->translatedFormat('j F').' - '.$fin->translatedFormat('j F Y');
        }

        return $debut->translatedFormat('j F Y').' - '.$fin->translatedFormat('j F Y');
    }

    public function urlPhoto(int $index, bool $miniature = false): string
    {
        return route('resultats.photo', [
            'resultat' => $this,
            'index' => $index,
            'taille' => $miniature ? 'miniature' : null,
            'v' => substr(md5((string) $this->updated_at), 0, 8),
        ]);
    }

    public function libelleJournal(): string
    {
        return 'Résultat « '.$this->titre.' »';
    }

    /**
     * @return array<string, string>
     */
    public static function libellesChampsJournal(): array
    {
        return [
            'date' => 'Date', 'date_fin' => 'Date de fin', 'titre' => 'Titre', 'contenu' => 'Texte',
            'photos' => 'Photos', 'visible' => 'Affiché',
        ];
    }
}
