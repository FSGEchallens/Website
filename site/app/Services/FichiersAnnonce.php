<?php

namespace App\Services;

use App\Models\Annonce;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Fichiers des annonces :
 * - l'image, réduite (1600 px au maximum) et réencodée en JPEG (métadonnées EXIF/GPS supprimées) ;
 * - les fichiers joints des boutons (PDF ou image), stockés hors du dossier public.
 * Les fichiers remplacés ou supprimés sont archivés dans le journal des modifications.
 */
class FichiersAnnonce
{
    private const TAILLE_MAX = 1600;

    public function enregistrerImage(Annonce $annonce, UploadedFile $fichier): string
    {
        $image = @imagecreatefromstring((string) file_get_contents($fichier->getRealPath()));

        if ($image === false) {
            throw ValidationException::withMessages(['image' => 'Cette image ne peut pas être lue. Essayez avec une image JPEG ou PNG.']);
        }

        $image = $this->redresser($image, $fichier);
        $largeur = imagesx($image);
        $hauteur = imagesy($image);
        $echelle = min(1, self::TAILLE_MAX / max($largeur, $hauteur));
        $nouvelleLargeur = max(1, (int) round($largeur * $echelle));
        $nouvelleHauteur = max(1, (int) round($hauteur * $echelle));

        $sortie = imagecreatetruecolor($nouvelleLargeur, $nouvelleHauteur);
        imagefill($sortie, 0, 0, (int) imagecolorallocate($sortie, 255, 255, 255));
        imagecopyresampled($sortie, $image, 0, 0, 0, 0, $nouvelleLargeur, $nouvelleHauteur, $largeur, $hauteur);

        ob_start();
        imagejpeg($sortie, null, 85);
        $jpeg = (string) ob_get_clean();

        $this->supprimerImage($annonce);

        $nom = 'annonce-'.$annonce->getKey().'-'.(Str::slug($annonce->titre) ?: 'image').'-'.Str::lower(Str::random(6)).'.jpg';
        Storage::disk(Annonce::DISQUE)->put(Annonce::DOSSIER_IMAGES.'/'.$nom, $jpeg);

        return $nom;
    }

    public function supprimerImage(Annonce $annonce): void
    {
        if ($annonce->image !== null) {
            $chemin = Annonce::DOSSIER_IMAGES.'/'.$annonce->image;
            $annonce->archiverFichier('image', Annonce::DISQUE, $chemin);
            Storage::disk(Annonce::DISQUE)->delete($chemin);
        }
    }

    /**
     * Enregistre un fichier joint (PDF, image) et renvoie [nom du fichier, nom d'origine].
     *
     * @return array{0: string, 1: string}
     */
    public function enregistrerFichier(Annonce $annonce, UploadedFile $fichier, string $libelle): array
    {
        $extension = strtolower($fichier->guessExtension() ?: $fichier->getClientOriginalExtension());
        $nom = 'annonce-'.$annonce->getKey().'-'.(Str::slug($libelle) ?: 'fichier').'-'.Str::lower(Str::random(6)).'.'.$extension;

        Storage::disk(Annonce::DISQUE)->putFileAs(Annonce::DOSSIER_FICHIERS, $fichier, $nom);

        $nomOriginal = Str::limit(basename($fichier->getClientOriginalName()), 120, '');

        return [$nom, $nomOriginal];
    }

    public function supprimerFichier(Annonce $annonce, string $fichier): void
    {
        $chemin = Annonce::DOSSIER_FICHIERS.'/'.basename($fichier);
        $annonce->archiverFichier('liens', Annonce::DISQUE, $chemin);
        Storage::disk(Annonce::DISQUE)->delete($chemin);
    }

    /**
     * Supprime tous les fichiers d'une annonce (avant sa suppression), en les archivant.
     */
    public function supprimerTout(Annonce $annonce): void
    {
        $this->supprimerImage($annonce);

        foreach ($annonce->liens ?? [] as $lien) {
            if (($lien['type'] ?? null) === 'fichier' && ! empty($lien['fichier'])) {
                $this->supprimerFichier($annonce, $lien['fichier']);
            }
        }
    }

    public function cheminImage(Annonce $annonce): ?string
    {
        return $annonce->image === null ? null : $this->cheminExistant(Annonce::DOSSIER_IMAGES.'/'.basename($annonce->image));
    }

    public function cheminFichier(?string $fichier): ?string
    {
        return $fichier === null ? null : $this->cheminExistant(Annonce::DOSSIER_FICHIERS.'/'.basename($fichier));
    }

    private function cheminExistant(string $chemin): ?string
    {
        $disque = Storage::disk(Annonce::DISQUE);

        return $disque->exists($chemin) ? $disque->path($chemin) : null;
    }

    /**
     * Redresse les photos prises de travers par un téléphone (orientation EXIF).
     */
    private function redresser(\GdImage $image, UploadedFile $fichier): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($fichier->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $angle = match ((int) (@exif_read_data($fichier->getRealPath())['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }
}
