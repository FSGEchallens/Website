<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Database\Factories\BatimentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bâtiment (ex. « Echallens, Court-Champ ») : adresse (rue, NPA, localité, pour la carte Google), jusqu'à 3 photos,
 * informations pratiques (accès, parking…) et les salles qu'il contient.
 * Les photos sont stockées hors du dossier public (storage/app/private/batiments) et servies par PhotoBatimentController.
 */
#[Fillable(['nom', 'rue', 'npa', 'localite', 'lien_carte', 'informations'])]
class Batiment extends Model
{
    /** @use HasFactory<BatimentFactory> */
    use HasFactory, Journalise;

    public const DISQUE = 'local';

    public const DOSSIER = 'batiments';

    public const PHOTOS_MAX = 3;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['photos' => 'array'];
    }

    /**
     * @return HasMany<Salle, $this>
     */
    public function salles(): HasMany
    {
        return $this->hasMany(Salle::class)->orderBy('ordre')->orderBy('nom');
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

    public function urlPhoto(int $index = 0, bool $miniature = false): ?string
    {
        $nom = $this->photos[$index] ?? null;

        if ($nom === null) {
            return null;
        }

        return route('batiments.photo', array_filter([
            'batiment' => $this,
            'index' => $index,
            'miniature' => $miniature ? 1 : null,
            'v' => substr(md5((string) $nom), 0, 8),
        ], fn ($valeur) => $valeur !== null));
    }

    public function libelleJournal(): string
    {
        return 'Bâtiment « '.$this->nom.' »';
    }

    /**
     * @return array<string, string>
     */
    public static function libellesChampsJournal(): array
    {
        return ['nom' => 'Nom', 'rue' => 'Rue', 'npa' => 'NPA', 'localite' => 'Localité', 'lien_carte' => 'Lien Google Maps', 'informations' => 'Informations', 'photos' => 'Photos'];
    }
}
