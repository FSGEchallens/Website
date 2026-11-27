<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Database\Factories\SalleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Salle de gym : nom, adresse (rue, NPA, localité, pour la carte Google), photo et informations pratiques (accès, parking…).
 * La photo est stockée hors du dossier public (storage/app/private/salles) et servie par PhotoSalleController.
 */
#[Fillable(['nom', 'rue', 'npa', 'localite', 'lien_carte', 'informations'])]
class Salle extends Model
{
    /** @use HasFactory<SalleFactory> */
    use HasFactory, Journalise;

    public const DISQUE = 'local';

    public const DOSSIER = 'salles';

    /**
     * @return HasMany<Cours, $this>
     */
    public function cours(): HasMany
    {
        return $this->hasMany(Cours::class);
    }

    /**
     * @return HasMany<SessionCours, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(SessionCours::class);
    }

    /**
     * Ex. « Route de Court-Champ 4, 1040 Echallens ».
     */
    public function adresseComplete(): string
    {
        return collect([$this->rue, trim($this->npa.' '.$this->localite)])->filter()->implode(', ');
    }

    /**
     * Carte Google intégrable (sans clé d'API), à partir de l'adresse.
     */
    public function urlCarteIntegree(): ?string
    {
        $recherche = $this->adresseComplete() ?: $this->nom;

        return $recherche ? 'https://maps.google.com/maps?q='.rawurlencode($recherche).'&z=16&output=embed' : null;
    }

    /**
     * Lien « Ouvrir dans Google Maps » : le lien saisi, sinon une recherche de l'adresse.
     */
    public function urlCarte(): ?string
    {
        if ($this->lien_carte) {
            return $this->lien_carte;
        }

        $recherche = $this->adresseComplete() ?: $this->nom;

        return $recherche ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($recherche) : null;
    }

    public function urlPhoto(bool $miniature = false): ?string
    {
        if ($this->photo === null) {
            return null;
        }

        return route('salles.photo', array_filter([
            'salle' => $this,
            'miniature' => $miniature ? 1 : null,
            'v' => substr(md5((string) $this->photo), 0, 8),
        ]));
    }

    public function libelleJournal(): string
    {
        return 'Salle « '.$this->nom.' »';
    }

    /**
     * @return array<string, string>
     */
    public static function libellesChampsJournal(): array
    {
        return ['nom' => 'Nom', 'rue' => 'Rue', 'npa' => 'NPA', 'localite' => 'Localité', 'lien_carte' => 'Lien Google Maps', 'informations' => 'Informations', 'photo' => 'Photo'];
    }
}
