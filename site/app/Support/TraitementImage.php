<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Réduit une image et la réencode en JPEG : les métadonnées (EXIF, GPS…) disparaissent,
 * et les photos prises de travers par un téléphone sont redressées.
 */
class TraitementImage
{
    /**
     * @return string Contenu du fichier JPEG.
     */
    public static function versJpeg(string $cheminSource, int $tailleMax, string $champErreur = 'photo', int $qualite = 82): string
    {
        $image = @imagecreatefromstring((string) file_get_contents($cheminSource));

        if ($image === false) {
            throw ValidationException::withMessages([$champErreur => 'Cette image ne peut pas être lue. Essayez avec une image JPEG ou PNG.']);
        }

        $image = self::redresser($image, $cheminSource);

        $largeur = imagesx($image);
        $hauteur = imagesy($image);
        $echelle = min(1, $tailleMax / max($largeur, $hauteur));
        $nouvelleLargeur = max(1, (int) round($largeur * $echelle));
        $nouvelleHauteur = max(1, (int) round($hauteur * $echelle));

        $sortie = imagecreatetruecolor($nouvelleLargeur, $nouvelleHauteur);
        imagefill($sortie, 0, 0, (int) imagecolorallocate($sortie, 255, 255, 255));
        imagecopyresampled($sortie, $image, 0, 0, 0, 0, $nouvelleLargeur, $nouvelleHauteur, $largeur, $hauteur);

        ob_start();
        imagejpeg($sortie, null, $qualite);

        return (string) ob_get_clean();
    }

    private static function redresser(\GdImage $image, string $chemin): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array(@mime_content_type($chemin), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $angle = match ((int) (@exif_read_data($chemin)['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }
}
