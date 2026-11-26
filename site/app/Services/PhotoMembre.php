<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Photos des membres : recadrées en carré, réduites à 600 × 600 et réencodées en JPEG,
 * ce qui supprime toutes les métadonnées (EXIF, GPS…). Stockées hors du dossier public.
 */
class PhotoMembre
{
    public const DISQUE = 'local';

    public const DOSSIER = 'photos-membres';

    public const TAILLE = 600;

    /**
     * Traite et enregistre la photo, remplace l'ancienne, et renvoie le nom du fichier.
     */
    public function enregistrer(User $membre, UploadedFile $fichier): string
    {
        $image = @imagecreatefromstring((string) file_get_contents($fichier->getRealPath()));

        if ($image === false) {
            throw ValidationException::withMessages(['photo' => 'Cette image ne peut pas être lue. Essayez avec une photo JPEG ou PNG.']);
        }

        $image = $this->redresser($image, $fichier);
        $carre = $this->recadrerEnCarre($image);

        ob_start();
        imagejpeg($carre, null, 85);
        $jpeg = (string) ob_get_clean();

        $nom = $this->nomDeFichier($membre);
        $disque = Storage::disk(self::DISQUE);

        if ($membre->photo !== null && $membre->photo !== $nom) {
            $disque->delete(self::DOSSIER.'/'.$membre->photo);
        }

        $disque->put(self::DOSSIER.'/'.$nom, $jpeg);

        return $nom;
    }

    /**
     * Renomme la photo si le nom ou le prénom du membre a changé. Renvoie le nom actuel du fichier.
     */
    public function renommerSiNecessaire(User $membre): ?string
    {
        if ($membre->photo === null) {
            return null;
        }

        $nouveauNom = $this->nomDeFichier($membre);
        $disque = Storage::disk(self::DISQUE);

        if ($nouveauNom !== $membre->photo && $disque->exists(self::DOSSIER.'/'.$membre->photo)) {
            $disque->move(self::DOSSIER.'/'.$membre->photo, self::DOSSIER.'/'.$nouveauNom);

            return $nouveauNom;
        }

        return $membre->photo;
    }

    public function supprimer(User $membre): void
    {
        if ($membre->photo !== null) {
            Storage::disk(self::DISQUE)->delete(self::DOSSIER.'/'.$membre->photo);
        }
    }

    public function chemin(User $membre): ?string
    {
        if ($membre->photo === null) {
            return null;
        }

        $chemin = self::DOSSIER.'/'.$membre->photo;

        return Storage::disk(self::DISQUE)->exists($chemin) ? Storage::disk(self::DISQUE)->path($chemin) : null;
    }

    /**
     * FSG-Echallens_Nom_Prénom.jpg (sans accents ni espaces). En cas d'homonyme, l'identifiant est ajouté.
     */
    public function nomDeFichier(User $membre): string
    {
        $nettoyer = fn (string $texte) => trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', Str::ascii($texte)), '-');

        $nom = 'FSG-Echallens_'.$nettoyer($membre->nom).'_'.$nettoyer($membre->prenom);

        $homonyme = User::where('photo', $nom.'.jpg')->whereKeyNot($membre->getKey())->exists();

        return ($homonyme ? $nom.'_'.$membre->getKey() : $nom).'.jpg';
    }

    /**
     * Les téléphones enregistrent souvent la photo couchée avec une indication d'orientation : on la redresse.
     */
    private function redresser(\GdImage $image, UploadedFile $fichier): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($fichier->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($fichier->getRealPath())['Orientation'] ?? 1);

        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }

    private function recadrerEnCarre(\GdImage $image): \GdImage
    {
        $largeur = imagesx($image);
        $hauteur = imagesy($image);
        $cote = min($largeur, $hauteur);
        $taille = min(self::TAILLE, $cote);

        $carre = imagecreatetruecolor($taille, $taille);
        imagefill($carre, 0, 0, (int) imagecolorallocate($carre, 255, 255, 255));
        imagecopyresampled(
            $carre, $image,
            0, 0, intdiv($largeur - $cote, 2), intdiv($hauteur - $cote, 2),
            $taille, $taille, $cote, $cote,
        );

        return $carre;
    }
}
