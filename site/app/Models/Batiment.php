<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Database\Factories\BatimentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bâtiment (ex. « Echallens, Court-Champ 3-4 ») : adresse (rue, NPA, localité), coordonnées GPS (la carte Google pointe sur le bâtiment), jusqu'à 3 photos,
 * informations pratiques (accès, parking…) et les salles qu'il contient.
 * Les photos sont stockées hors du dossier public (storage/app/private/batiments) et servies par PhotoBatimentController.
 */
#[Fillable(['nom', 'rue', 'npa', 'localite', 'latitude', 'longitude', 'lien_carte', 'informations'])]
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
        return ['photos' => 'array', 'latitude' => 'float', 'longitude' => 'float'];
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
     * Lit des coordonnées copiées depuis Google Maps (« 46.636370, 6.633660 ») ; null si le texte n'en est pas.
     *
     * @return array{0: float, 1: float}|null
     */
    public static function lireCoordonnees(string $texte): ?array
    {
        if (! preg_match('/^\s*(-?\d{1,2}(?:\.\d+)?)\s*[,; ]\s*(-?\d{1,3}(?:\.\d+)?)\s*$/', $texte, $m)) {
            return null;
        }

        [$latitude, $longitude] = [(float) $m[1], (float) $m[2]];

        return abs($latitude) <= 90 && abs($longitude) <= 180 ? [round($latitude, 6), round($longitude, 6)] : null;
    }

    public function aDesCoordonnees(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Ex. « 46.636370, 6.633660 » (format accepté par Google Maps).
     */
    public function coordonnees(): ?string
    {
        return $this->aDesCoordonnees()
            ? number_format((float) $this->latitude, 6, '.', '').', '.number_format((float) $this->longitude, 6, '.', '')
            : null;
    }

    /**
     * Ce que Google Maps doit chercher : les coordonnées (point exact sur le bâtiment), sinon l'adresse.
     */
    private function rechercheCarte(): string
    {
        return $this->aDesCoordonnees()
            ? str_replace(' ', '', (string) $this->coordonnees())
            : ($this->adresseComplete() ?: (string) $this->nom);
    }

    /**
     * Carte Google intégrable (sans clé d'API), centrée sur les coordonnées ou, à défaut, sur l'adresse.
     */
    public function urlCarteIntegree(): ?string
    {
        $recherche = $this->rechercheCarte();

        return $recherche !== '' ? 'https://maps.google.com/maps?q='.rawurlencode($recherche).'&z=17&output=embed' : null;
    }

    /**
     * Lien « Ouvrir dans Google Maps » : le lien saisi, sinon les coordonnées, sinon l'adresse.
     */
    public function urlCarte(): ?string
    {
        if ($this->lien_carte) {
            return $this->lien_carte;
        }

        $recherche = $this->rechercheCarte();

        return $recherche !== '' ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($recherche) : null;
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
        return ['nom' => 'Nom', 'rue' => 'Rue', 'npa' => 'NPA', 'localite' => 'Localité', 'latitude' => 'Latitude', 'longitude' => 'Longitude', 'lien_carte' => 'Lien Google Maps', 'informations' => 'Informations', 'photos' => 'Photos'];
    }
}
