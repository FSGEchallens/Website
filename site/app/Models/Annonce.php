<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Database\Factories\AnnonceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Annonce de la page d'accueil (sous le bandeau).
 *
 * @property array<int, array{libelle: string, type: string, url?: ?string, fichier?: ?string, nom_original?: ?string}>|null $liens
 */
#[Fillable(['titre', 'contenu', 'position_image', 'debut_le', 'fin_le', 'visible'])]
class Annonce extends Model
{
    /** @use HasFactory<AnnonceFactory> */
    use HasFactory, Journalise;

    public const POSITIONS_IMAGE = ['gauche' => 'À gauche', 'droite' => 'À droite', 'fond' => 'En fond'];

    public const DISQUE = 'local';

    public const DOSSIER_IMAGES = 'annonces/images';

    public const DOSSIER_FICHIERS = 'annonces/fichiers';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'liens' => 'array',
            'debut_le' => 'date',
            'fin_le' => 'date',
            'visible' => 'boolean',
            'ordre' => 'integer',
        ];
    }

    /**
     * @param  Builder<Annonce>  $query
     */
    public function scopeDansLOrdre(Builder $query): void
    {
        $query->orderBy('ordre')->orderByDesc('id');
    }

    /**
     * Annonces à afficher aujourd'hui (visibles et dans leur période d'affichage).
     *
     * @param  Builder<Annonce>  $query
     */
    public function scopeActives(Builder $query): void
    {
        $aujourdhui = now()->toDateString();

        $query->where('visible', true)
            ->where(fn (Builder $q) => $q->whereNull('debut_le')->orWhere('debut_le', '<=', $aujourdhui))
            ->where(fn (Builder $q) => $q->whereNull('fin_le')->orWhere('fin_le', '>=', $aujourdhui));
    }

    /**
     * Attention : à l'intérieur du modèle, $this->visible désigne une propriété interne de Laravel
     * (liste des champs visibles en JSON), pas la colonne « visible ». On lit donc la colonne explicitement.
     */
    public function estAffichable(): bool
    {
        return (bool) $this->getAttribute('visible');
    }

    public function estActive(): bool
    {
        return $this->estAffichable()
            && ($this->debut_le === null || ! $this->debut_le->isFuture())
            && ($this->fin_le === null || $this->fin_le->endOfDay()->isFuture());
    }

    /**
     * État lisible pour la page de gestion.
     */
    public function etat(): string
    {
        return match (true) {
            ! $this->estAffichable() => 'Masquée',
            $this->debut_le !== null && $this->debut_le->isFuture() => 'Programmée (dès le '.$this->debut_le->format('d.m.Y').')',
            $this->fin_le !== null && ! $this->fin_le->endOfDay()->isFuture() => 'Terminée (le '.$this->fin_le->format('d.m.Y').')',
            default => 'Affichée',
        };
    }

    public function urlImage(): ?string
    {
        return $this->image === null ? null : route('annonces.image', ['annonce' => $this, 'v' => substr(md5((string) $this->updated_at), 0, 8)]);
    }

    /**
     * Adresse d'un bouton-lien : le site externe, ou le fichier joint.
     */
    public function urlLien(int $index): ?string
    {
        $lien = $this->liens[$index] ?? null;

        if ($lien === null) {
            return null;
        }

        return $lien['type'] === 'fichier'
            ? route('annonces.fichier', ['annonce' => $this, 'index' => $index])
            : ($lien['url'] ?? null);
    }

    public function libelleJournal(): string
    {
        return 'Annonce « '.$this->titre.' »';
    }

    /**
     * @return array<string, string>
     */
    public static function libellesChampsJournal(): array
    {
        return [
            'titre' => 'Titre', 'contenu' => 'Texte', 'image' => 'Image', 'position_image' => 'Position de l\'image',
            'liens' => 'Liens', 'debut_le' => 'Affichée dès le', 'fin_le' => 'Affichée jusqu\'au',
            'visible' => 'Affichée', 'ordre' => 'Ordre',
        ];
    }
}
