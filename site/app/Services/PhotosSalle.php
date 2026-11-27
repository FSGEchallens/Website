<?php

namespace App\Services;

use App\Models\Salle;
use App\Support\TraitementImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photos d'une salle (3 au maximum) : une version 1600 px et une miniature 480 px, en JPEG sans métadonnées
 * (ni EXIF ni GPS). Une photo retirée est archivée dans le journal avant d'être supprimée.
 */
class PhotosSalle
{
    private const TAILLE = 1600;

    private const TAILLE_MINIATURE = 480;

    /**
     * Traite et enregistre une photo, et renvoie le nom du fichier.
     */
    public function enregistrer(Salle $salle, string $cheminSource): string
    {
        $photo = TraitementImage::versJpeg($cheminSource, self::TAILLE, 'nouvellesPhotos');
        $miniature = TraitementImage::versJpeg($cheminSource, self::TAILLE_MINIATURE, 'nouvellesPhotos', 75);

        $nom = 'salle-'.(Str::slug(Str::limit($salle->nom, 40, '')) ?: 'photo').'-'.Str::lower(Str::random(6)).'.jpg';
        $disque = Storage::disk(Salle::DISQUE);
        $disque->put(Salle::DOSSIER.'/'.$nom, $photo);
        $disque->put(Salle::DOSSIER.'/'.self::nomMiniature($nom), $miniature);

        return $nom;
    }

    public function supprimer(Salle $salle, string $nom): void
    {
        $nom = basename($nom);
        $salle->archiverFichier('photos', Salle::DISQUE, Salle::DOSSIER.'/'.$nom);
        Storage::disk(Salle::DISQUE)->delete([Salle::DOSSIER.'/'.$nom, Salle::DOSSIER.'/'.self::nomMiniature($nom)]);
    }

    public function supprimerTout(Salle $salle): void
    {
        foreach ($salle->photos ?? [] as $nom) {
            $this->supprimer($salle, $nom);
        }
    }

    /**
     * Chemin de la photo (ou de sa miniature ; à défaut de miniature, la photo elle-même).
     */
    public function chemin(Salle $salle, int $index, bool $miniature = false): ?string
    {
        $nom = $salle->photos[$index] ?? null;

        if ($nom === null) {
            return null;
        }

        $disque = Storage::disk(Salle::DISQUE);
        $nom = basename($nom);
        $candidats = $miniature ? [self::nomMiniature($nom), $nom] : [$nom];

        foreach ($candidats as $candidat) {
            if ($disque->exists(Salle::DOSSIER.'/'.$candidat)) {
                return $disque->path(Salle::DOSSIER.'/'.$candidat);
            }
        }

        return null;
    }

    public static function nomMiniature(string $nom): string
    {
        return preg_replace('/\.jpg$/', '_miniature.jpg', $nom) ?? $nom;
    }
}
