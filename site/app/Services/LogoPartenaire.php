<?php

namespace App\Services;

use App\Models\Partenaire;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Logos des partenaires : réduits (400 × 160 au maximum), réencodés en PNG (transparence conservée,
 * métadonnées supprimées) et stockés hors du dossier public.
 */
class LogoPartenaire
{
    public const DISQUE = 'local';

    public const DOSSIER = 'logos-partenaires';

    private const LARGEUR_MAX = 400;

    private const HAUTEUR_MAX = 160;

    /**
     * Traite et enregistre le logo, supprime l'ancien logo importé, et renvoie le nom du fichier.
     */
    public function enregistrer(Partenaire $partenaire, UploadedFile $fichier): string
    {
        $image = @imagecreatefromstring((string) file_get_contents($fichier->getRealPath()));

        if ($image === false) {
            throw ValidationException::withMessages(['logo' => 'Cette image ne peut pas être lue. Essayez avec un fichier PNG ou JPEG.']);
        }

        $largeur = imagesx($image);
        $hauteur = imagesy($image);
        $echelle = min(1, self::LARGEUR_MAX / $largeur, self::HAUTEUR_MAX / $hauteur);
        $nouvelleLargeur = max(1, (int) round($largeur * $echelle));
        $nouvelleHauteur = max(1, (int) round($hauteur * $echelle));

        $logo = imagecreatetruecolor($nouvelleLargeur, $nouvelleHauteur);
        imagealphablending($logo, false);
        imagesavealpha($logo, true);
        imagefill($logo, 0, 0, (int) imagecolorallocatealpha($logo, 255, 255, 255, 127));
        imagecopyresampled($logo, $image, 0, 0, 0, 0, $nouvelleLargeur, $nouvelleHauteur, $largeur, $hauteur);

        ob_start();
        imagepng($logo, null, 9);
        $png = (string) ob_get_clean();

        $this->supprimer($partenaire);

        $nom = 'partenaire-'.(Str::slug($partenaire->nom) ?: 'logo').'-'.Str::lower(Str::random(6)).'.png';
        Storage::disk(self::DISQUE)->put(self::DOSSIER.'/'.$nom, $png);

        return $nom;
    }

    /**
     * Supprime le logo importé (les logos d'origine du dossier public ne sont jamais supprimés).
     */
    public function supprimer(Partenaire $partenaire): void
    {
        if ($partenaire->logo !== null && ! $partenaire->aUnLogoDOrigine()) {
            Storage::disk(self::DISQUE)->delete(self::DOSSIER.'/'.$partenaire->logo);
        }
    }

    public function chemin(Partenaire $partenaire): ?string
    {
        if ($partenaire->logo === null || $partenaire->aUnLogoDOrigine()) {
            return null;
        }

        $chemin = self::DOSSIER.'/'.basename($partenaire->logo);

        return Storage::disk(self::DISQUE)->exists($chemin) ? Storage::disk(self::DISQUE)->path($chemin) : null;
    }
}
